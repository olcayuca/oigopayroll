<?php

namespace App\Assistant;

use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Models\Firm;
use App\Models\User;
use Throwable;

/**
 * Panel kurulum asistanı: answers questions about the firm's setup only (şirket, işyeri, tanımlar,
 * personel, kurulum dosyası / Excel aktarımı, sihirbaz) with Claude. Other topics are declined.
 *
 * The system prompt (rules + the setup-file reference) is stable and cached; the firm's current
 * status (SetupContext) follows it. Identifiers typed by the user (TCKN, IBAN) are masked before
 * anything leaves the server.
 */
class SetupAssistant
{
    public const MAX_HISTORY = 12;

    public static function configured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * @param  list<array{role: 'user'|'assistant', text: string}>  $history  ending with the user's question
     */
    public function reply(User $user, Firm $firm, array $history): string
    {
        $messages = array_map(
            fn (array $message) => ['role' => $message['role'], 'content' => $message['role'] === 'user' ? self::redact($message['text']) : $message['text']],
            array_slice($history, -self::MAX_HISTORY),
        );

        // The API expects the conversation to start with the user.
        while ($messages !== [] && $messages[0]['role'] !== 'user') {
            array_shift($messages);
        }

        try {
            return $this->send([
                ['type' => 'text', 'text' => self::instructions(), 'cacheControl' => ['type' => 'ephemeral']],
                ['type' => 'text', 'text' => "Kullanıcının aktif firmasının güncel kurulum durumu:\n".SetupContext::for($user, $firm)],
            ], $messages);
        } catch (RateLimitException) {
            return 'Şu an çok fazla istek var; lütfen bir dakika sonra tekrar deneyin.';
        } catch (APIConnectionException|APIStatusException $e) {
            report($e);

            return 'Asistana şu an ulaşılamıyor. Lütfen biraz sonra tekrar deneyin.';
        } catch (Throwable $e) {
            report($e);

            return 'Beklenmeyen bir hata oluştu. Lütfen biraz sonra tekrar deneyin.';
        }
    }

    /**
     * Call Claude. Separate so tests can replace it.
     *
     * @param  list<array{type: 'text', text: string, cacheControl?: array{type: 'ephemeral'}}>  $system
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     */
    protected function send(array $system, array $messages): string
    {
        $effort = match (config('services.anthropic.effort')) {
            'medium' => 'medium',
            'high' => 'high',
            default => 'low',
        };
        $client = new Client(apiKey: (string) config('services.anthropic.key'));

        $response = $client->beta->messages->create(
            model: (string) config('services.anthropic.model', 'claude-opus-5-5'),
            maxTokens: 8000,
            system: $system,
            messages: $messages,
            outputConfig: ['effort' => $effort],
            // On a policy decline the API retries on its default fallback model within the same call.
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );

        if ($response->stopReason === 'refusal') {
            return 'Bu soruya yanıt veremiyorum. Kurulumla ilgili başka bir konuda yardımcı olabilirim.';
        }

        $text = '';
        foreach ($response->content as $block) {
            if ($block instanceof BetaTextBlock) {
                $text .= $block->text;
            }
        }

        return trim($text) !== '' ? trim($text) : 'Yanıt üretilemedi; sorunuzu biraz daha açarak tekrar sorabilir misiniz?';
    }

    /**
     * Mask 11-digit numbers (TCKN) and IBANs typed into a question.
     */
    public static function redact(string $text): string
    {
        $text = (string) preg_replace('/\bTR\s?\d{2}(?:\s?\d{4}){5}\s?\d{2}\b/i', '[IBAN gizlendi]', $text);

        return (string) preg_replace('/(?<!\d)\d{11}(?!\d)/', '[11 haneli numara gizlendi]', $text);
    }

    /**
     * Stable system prompt: scope, behaviour and the setup-file reference.
     */
    public static function instructions(): string
    {
        $reference = @file_get_contents(base_path('docs/KURULUM_DOSYASI.md')) ?: '';

        return <<<PROMPT
        Sen HRD Bordro panelinin Kurulum Asistanı'sın. Müşteri firmaların kullanıcılarına ve HRD bordro uzmanlarına,
        firmanın sisteme kurulumu konusunda Türkçe, kısa ve net yardım edersin.

        Kapsamın yalnızca kurulumdur:
        - Şirketler: oluşturma, alanlar, Excel ile şirket aktarımı.
        - İşyerleri: kurulum dosyasının "Firma Bilgileri" sayfasındaki 36 zorunlu alan (Genel, Vergi, SGK, İŞKUR, Emniyet & BES, Adres),
          ek bilgiler, şifrelerin şifreli saklanması, kurulum tamamlanma oranı.
        - Tanımlar: üst birim, birim, iş ailesi, unvan, pozisyon, seviye, masraf grubu.
        - Personel: kurulum dosyasının "Personel Bilgileri" sayfası (68 alan, 8 sekme), zorunlu alanlar, alanların anlamı ve doğru biçimi.
        - Excel kurulum dosyası ve aktarım: şablon, müşteri dosyasının olduğu gibi yüklenmesi, önizleme hataları, güncelleme kuralları.
        - Kurulum Sihirbazı, gösterge panelindeki kurulum adımları ve uyarılar, İşlem Geçmişi'nde kurulum değişikliklerini bulma.

        Kapsam dışı: bordro / ücret / vergi / SGK prim hesaplamaları, mevzuat yorumu, hukuki ya da mali tavsiye, kurulumla ilgisiz
        her konu. Böyle bir soruda kibarca bu asistanın yalnızca kurulum konularında yardımcı olduğunu söyle ve sorunun firmanın sorumlu
        bordro uzmanına sorulmasını öner. Bir alanın ne anlama geldiğini ve hangi biçimde girileceğini açıklamak kapsam içidir.

        Nasıl yanıt verirsin:
        - Panelde işlem yapamazsın; kullanıcıya nereye gideceğini menü yoluyla söyle (ör. "Kurulum › İşyerleri › işyerini aç › Düzenle › SGK sekmesi").
        - Firmanın güncel kurulum durumu aşağıda verilir; "neler eksik", "hangi işyerinde eksik var" gibi sorularda onu kullan.
          Orada olmayan bir bilgiyi uydurma; emin değilsen ilgili ekranı göster.
        - Şifre, TCKN, IBAN gibi bilgileri asla isteme ve tekrar etme; kullanıcı yazarsa bunları sohbete yazmamasını hatırlat.
        - Kısa yaz: genellikle birkaç cümle veya kısa bir madde listesi. Gerektiğinde alan adlarını ekrandaki gibi kullan.

        Panel menüsü: Kurulum (Şirketler, İşyerleri, Belgeler, Personel, Tanımlar, Kurulum Sihirbazı), Operasyon (Gösterge Paneli),
        Yönetim (Kullanıcılar & Yetkiler, Firma Erişimleri, İşlem Geçmişi, Bildirimler, Çöp Kutusu, Ayarlar).
        Excel aktarımı: ilgili listede "Excel ile Aktar" › şablonu indir ya da müşterinin KURULUM DOSYASI'nı yükle › önizlemede hataları düzelt › onayla.
        Onay verilmeden hiçbir kayıt oluşmaz. İşyeri satırları şirket numarası + işyeri numarasıyla, personel satırları sicil numarasıyla
        eşleşip güncellenir. Zorunlu sütunlar dosyada olmalıdır; boş bırakılan şifre / TCKN / IBAN hücreleri mevcut değeri korur.

        Kurulum dosyası başvuru metni:
        {$reference}
        PROMPT;
    }
}
