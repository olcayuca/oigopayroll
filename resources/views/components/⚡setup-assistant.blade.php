<?php

use App\Assistant\SetupAssistant;
use App\Models\Firm;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * Floating setup assistant (OigoAsistan): answers setup questions for the active firm only, plus
 * shortcuts (note, reminder, calculator) opened as popups. The conversation lives in the session, per firm.
 */
new class extends Component {
    public const MAX_LENGTH = 1000;

    public const CHIPS = [
        'İşyerlerimde hangi kurulum alanları eksik?',
        'Kurulum dosyasını (Excel) nasıl yüklerim?',
        'Personel kaydı için zorunlu alanlar neler?',
    ];

    public string $question = '';

    #[Computed]
    public function firm(): ?Firm
    {
        return Auth::user()?->activeFirm();
    }

    /**
     * @return list<array{role: 'user'|'assistant', text: string}>
     */
    #[Computed]
    public function messages(): array
    {
        return session($this->sessionKey(), []);
    }

    public function ask(?string $question = null): void
    {
        $user = Auth::user();
        $firm = $this->firm;
        $question = trim($question ?? $this->question);
        abort_unless($user && $firm, 403);

        if ($question === '') {
            return;
        }

        $this->question = '';
        $messages = [...$this->messages, ['role' => 'user', 'text' => SetupAssistant::redact(Str::limit($question, self::MAX_LENGTH, ''))]];

        $answer = ! SetupAssistant::configured()
            ? (app()->isLocal()
                ? 'Asistan henüz etkinleştirilmedi: sunucuda ANTHROPIC_API_KEY tanımlı değil (.env). Anahtar eklendiğinde sorularınızı yanıtlayacağım.'
                : 'Asistan şu an soruları yanıtlayamıyor. Not, hatırlatıcı ve hesap makinesi kısayollarını kullanabilirsiniz.')
            : (RateLimiter::attempt('setup-assistant:'.$user->id, 20, fn () => true, 300)
                ? app(SetupAssistant::class)->reply($user, $firm, $messages)
                : 'Kısa sürede çok fazla soru sordunuz. Lütfen birkaç dakika sonra tekrar deneyin.');

        $messages[] = ['role' => 'assistant', 'text' => $answer];
        session([$this->sessionKey() => array_slice($messages, -SetupAssistant::MAX_HISTORY * 2)]);
        unset($this->messages);

        $this->dispatch('setup-assistant-updated');
    }

    public function restart(): void
    {
        session()->forget($this->sessionKey());
        unset($this->messages);
    }

    private function sessionKey(): string
    {
        return 'setup-assistant.'.($this->firm->id ?? 0);
    }
}; ?>

<div x-data="{ open: false, pending: '' }" x-on:keydown.escape.window="document.querySelector('dialog[open]') || (open = false)"
    x-on:setup-assistant-updated.window="pending = ''; $nextTick(() => $refs.log && ($refs.log.scrollTop = $refs.log.scrollHeight))"
    data-test="setup-assistant">
    <div x-cloak x-show="open" x-transition.opacity.duration.150ms
        x-effect="open && $nextTick(() => { $refs.log.scrollTop = $refs.log.scrollHeight; $refs.input.focus() })"
        class="fixed bottom-[100px] end-4 z-[45] flex h-[560px] max-h-[calc(100vh-130px)] w-[400px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-[18px] border border-[#E5EAF1] bg-white shadow-[0_20px_50px_rgba(16,40,72,0.22)] sm:end-7">
        <div class="flex items-center gap-3 bg-gradient-to-r from-[#0E2038] to-[#143a5e] px-[18px] py-4">
            <div class="flex size-[38px] shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-mint to-[#6FE3CF] text-sm font-extrabold text-[#0E2038]">O</div>
            <div class="min-w-0 flex-1">
                <div class="text-[14.5px] font-extrabold text-white">OigoAsistan · Kurulum</div>
                <div class="truncate text-[11.5px] font-semibold text-[#9DB2CF]">{{ $this->firm?->name }} · yalnızca kurulum soruları</div>
            </div>
            <button type="button" wire:click="restart" title="Yeni sohbet" aria-label="Yeni sohbet"
                class="flex size-8 items-center justify-center rounded-lg bg-white/[0.08] text-white hover:bg-white/[0.16]">
                <flux:icon.arrow-path class="size-4" />
            </button>
            <button type="button" x-on:click="open = false" aria-label="Kapat"
                class="flex size-8 items-center justify-center rounded-lg bg-white/[0.08] text-white hover:bg-white/[0.16]">
                <flux:icon.x-mark class="size-4" />
            </button>
        </div>

        <div class="grid grid-cols-3 gap-2 border-b border-[#EEF2F7] bg-white px-3 py-2.5" data-test="assistant-shortcuts">
            @foreach ([
                ['assistant-notes', 'pencil-square', 'Not ekle'],
                ['assistant-reminders', 'bell-alert', 'Hatırlatıcı'],
                ['assistant-calculator', 'calculator', 'Hesap makinesi'],
            ] as [$modal, $icon, $label])
                <button type="button" x-on:click="$flux.modal('{{ $modal }}').show()"
                    class="flex flex-col items-center gap-1 rounded-[10px] border-[1.5px] border-[#E0E6EE] bg-white px-1 py-2 text-[11.5px] font-bold text-brand transition hover:border-brand hover:bg-[#F4F8FD]">
                    <flux:icon :icon="$icon" class="size-[18px] text-mint" />
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if (! SetupAssistant::configured())
            <div class="border-b border-[#F3E2BF] bg-[#FFF8EA] px-4 py-2.5 text-[12px] font-semibold leading-snug text-[#8A5A14]" data-test="assistant-not-configured">
                @if (app()->isLocal())
                    Asistan henüz etkinleştirilmedi: <code>.env</code> dosyasına <code>ANTHROPIC_API_KEY</code> eklenmeli.
                @else
                    Asistan şu an soruları yanıtlayamıyor; kısayollar kullanılabilir.
                @endif
            </div>
        @endif

        <div x-ref="log" class="flex flex-1 flex-col gap-2.5 overflow-y-auto bg-[#F7F9FC] p-4">
            <div class="flex">
                <div class="max-w-[88%] rounded-[14px] rounded-bl-[4px] border border-[#E8EDF3] bg-white px-3.5 py-2.5 text-[13px] leading-relaxed text-ink">
                    Merhaba! Ben kurulum asistanıyım. Şirket, işyeri, tanımlar, personel ve Excel kurulum dosyası hakkında sorularınızı yanıtlarım.
                    Bordro hesaplama ve mevzuat soruları için bordro uzmanınıza başvurun.
                </div>
            </div>

            @foreach ($this->messages as $message)
                @if ($message['role'] === 'user')
                    <div class="flex justify-end" wire:key="m-{{ $loop->index }}">
                        <div class="max-w-[85%] whitespace-pre-line rounded-[14px] rounded-br-[4px] bg-brand px-3.5 py-2.5 text-[13px] leading-relaxed text-white">{{ $message['text'] }}</div>
                    </div>
                @else
                    <div class="flex" wire:key="m-{{ $loop->index }}" data-test="assistant-answer">
                        <div class="assistant-answer max-w-[88%] rounded-[14px] rounded-bl-[4px] border border-[#E8EDF3] bg-white px-3.5 py-2.5 text-[13px] leading-relaxed text-ink [&_li]:ms-4 [&_ol]:list-decimal [&_p+p]:mt-2 [&_strong]:font-bold [&_ul]:mt-1 [&_ul]:list-disc [&_ol]:mt-1">
                            {!! Str::markdown($message['text'], ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                        </div>
                    </div>
                @endif
            @endforeach

            <div wire:loading.flex wire:target="ask" class="flex-col gap-2.5">
                <div class="flex justify-end" x-show="pending">
                    <div class="max-w-[85%] whitespace-pre-line rounded-[14px] rounded-br-[4px] bg-brand px-3.5 py-2.5 text-[13px] leading-relaxed text-white" x-text="pending"></div>
                </div>
                <div class="flex">
                    <div class="rounded-[14px] border border-[#E8EDF3] bg-white px-3.5 py-2.5 text-[13px] font-semibold text-ink-4">Yazıyor…</div>
                </div>
            </div>

            @if ($this->messages === [])
                <div class="mt-1 flex flex-col gap-[7px]" wire:loading.remove wire:target="ask">
                    @foreach (self::CHIPS as $chip)
                        <button type="button" x-on:click="pending = @js($chip); $wire.ask(@js($chip))"
                            class="rounded-[10px] border-[1.5px] border-[#E0E6EE] bg-white px-3 py-[9px] text-start text-[12.5px] font-bold text-brand hover:border-brand">{{ $chip }}</button>
                    @endforeach
                </div>
            @endif
        </div>

        <form wire:submit="ask" x-on:submit="pending = $refs.input.value" class="border-t border-[#EEF2F7] p-3">
            <div class="flex gap-2">
                <input x-ref="input" type="text" wire:model="question" maxlength="{{ self::MAX_LENGTH }}" autocomplete="off"
                    placeholder="Kurulumla ilgili sorunuzu yazın…" aria-label="Soru"
                    class="h-[42px] min-w-0 flex-1 rounded-[10px] border-[1.5px] border-[#E0E6EE] px-[13px] text-[13.5px] text-ink outline-none focus:border-brand">
                <button type="submit" aria-label="Gönder" wire:loading.attr="disabled" wire:target="ask"
                    class="flex size-[42px] shrink-0 items-center justify-center rounded-[10px] bg-brand text-white hover:bg-[#1d4c82] disabled:opacity-60">
                    <flux:icon.paper-airplane class="size-[17px]" />
                </button>
            </div>
            <p class="mt-2 text-[11px] leading-snug text-ink-4">Sorularınız yanıt üretmek için yapay zekâ hizmetine (Anthropic) iletilir. TCKN, IBAN veya şifre yazmayın.</p>
        </form>
    </div>

    <button type="button" x-on:click="open = ! open" title="OigoAsistan" aria-label="Kurulum asistanı" data-test="setup-assistant-toggle"
        class="fixed bottom-7 end-4 z-[46] flex size-[58px] items-center justify-center rounded-full bg-gradient-to-br from-brand to-mint text-white shadow-[0_10px_26px_rgba(22,59,102,0.35)] transition-transform hover:scale-[1.06] sm:end-7">
        <flux:icon.chat-bubble-left-ellipsis class="size-6" x-show="! open" />
        <flux:icon.x-mark class="size-6" x-show="open" x-cloak />
    </button>

    <livewire:assistant-tools />
</div>
