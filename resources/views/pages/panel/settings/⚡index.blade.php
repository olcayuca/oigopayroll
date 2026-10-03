<?php

use App\Concerns\ProfileValidationRules;
use App\Enums\AuditEvent;
use App\Livewire\PanelComponent;
use App\Notifications\HrdNotification;
use App\Notifications\NotificationCategory;
use App\Support\Audit;
use App\Support\AuditChanges;
use App\Support\FirmSettings;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/*
 * Panel → Ayarlar (prototype 35-ayarlar): firm settings for users who may update the firm,
 * personal settings (notifications, security, preferences) for everyone.
 */
new #[Title('Ayarlar')] class extends PanelComponent {
    use ProfileValidationRules, WithFileUploads;

    /** slug => [title, description, firm-level] ; onay: payroll module (YAKINDA) */
    public const TABS = [
        'sirket' => ['Şirket Bilgileri', 'Firmanın unvan ve iletişim bilgileri. Şirket ve işyeri detayları kendi ekranlarından yönetilir.', true],
        'bordro' => ['Bordro Varsayılanları', 'Yeni dönem ve personel kayıtlarında kullanılacak varsayılan değerler.', true],
        'onay' => ['Onay Akışı', 'Bordronun kapatılmadan önce geçeceği onay kademeleri.', true],
        'bildirim' => ['Bildirimler', 'Hangi olaylarda e-posta ve panel bildirimi alacağınızı seçin.', false],
        'guvenlik' => ['Güvenlik', 'Firma genelinde geçerli erişim kuralları ve hesabınızın güvenliği.', false],
        'marka' => ['Marka & Logo', 'Panelde ve ileride PDF çıktılarında kullanılacak firma logosu.', true],
        'tercih' => ['Tercihlerim', 'Yalnızca sizin hesabınız için geçerli bilgiler ve görünüm.', false],
    ];

    #[Url(as: 'sekme', except: 'sirket')]
    public string $tab = 'sirket';

    /** @var array<string, string|null> */
    public array $firmForm = [];

    /** @var array<string, string|bool> */
    public array $payroll = [];

    /** @var array<string, array<string, bool>> */
    public array $prefs = [];

    public bool $requireTwoFactor = false;

    /** @var TemporaryUploadedFile|null */
    public $logo = null;

    public string $name = '';

    public string $email = '';

    public function mount(): void
    {
        if (! array_key_exists($this->tab, self::TABS) || $this->tab === 'onay') {
            $this->tab = 'sirket';
        }

        $firm = $this->firm;
        $this->firmForm = $firm->only(['contact_name', 'phone', 'email', 'kep_address', 'website', 'mersis_no', 'address']);
        $this->payroll = FirmSettings::payroll($firm);
        $this->requireTwoFactor = FirmSettings::requiresTwoFactor($firm);

        $user = Auth::user();
        foreach (NotificationCategory::configurable() as $category) {
            $this->prefs[$category->value] = ['mail' => $user->wantsNotification($category, 'mail'), 'panel' => $user->wantsNotification($category, 'panel')];
        }
        $this->name = $user->name;
        $this->email = $user->email;
    }

    #[Computed]
    public function canManage(): bool
    {
        return Auth::user()->can('update', $this->firm);
    }

    public function save(): void
    {
        match ($this->tab) {
            'sirket' => $this->saveFirm(),
            'bordro' => $this->savePayroll(),
            'bildirim' => $this->savePreferences(),
            'guvenlik' => $this->saveSecurity(),
            'tercih' => $this->saveProfile(),
            default => null,
        };
    }

    public function removeLogo(): void
    {
        $this->authorize('update', $this->firm);

        if ($this->firm->logo_path) {
            Storage::disk('local')->delete($this->firm->logo_path);
            $this->firm->forceFill(['logo_path' => null])->save();
            Audit::log(AuditEvent::FirmUpdated, "Firma logosu kaldırıldı: {$this->firm->name}", $this->firm);
        }
    }

    public function updatedLogo(): void
    {
        $this->authorize('update', $this->firm);
        $this->validate(['logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:max_width=2000,max_height=2000']], [], ['logo' => 'Logo']);

        $old = $this->firm->logo_path;
        $path = $this->logo->store('firm-logos', 'local');
        $this->firm->forceFill(['logo_path' => $path])->save();

        if ($old) {
            Storage::disk('local')->delete($old);
        }

        $this->reset('logo');
        Audit::log(AuditEvent::FirmUpdated, "Firma logosu güncellendi: {$this->firm->name}", $this->firm);
        Flux::toast(variant: 'success', text: 'Logo güncellendi.');
    }

    private function saveFirm(): void
    {
        $this->authorize('update', $this->firm);

        $data = $this->validate([
            'firmForm.contact_name' => ['nullable', 'string', 'max:255'],
            'firmForm.phone' => ['nullable', 'string', 'max:30'],
            'firmForm.email' => ['nullable', 'email', 'max:255'],
            'firmForm.kep_address' => ['nullable', 'email', 'max:255'],
            'firmForm.website' => ['nullable', 'string', 'max:255', 'regex:/^(https?:\/\/)?[\w.-]+\.[a-z]{2,}(\/\S*)?$/i'],
            'firmForm.mersis_no' => ['nullable', 'regex:/^\d{16}$/'],
            'firmForm.address' => ['nullable', 'string', 'max:500'],
        ], ['firmForm.mersis_no.regex' => 'MERSİS no 16 haneli olmalı.', 'firmForm.website.regex' => 'Web sitesi adresi geçerli değil.'], [
            'firmForm.contact_name' => 'Yetkili kişi', 'firmForm.phone' => 'Telefon', 'firmForm.email' => 'E-posta',
            'firmForm.kep_address' => 'KEP adresi', 'firmForm.website' => 'Web sitesi', 'firmForm.address' => 'Yazışma adresi',
        ])['firmForm'];

        $firm = $this->firm;
        $before = AuditChanges::snapshot($firm);
        $firm->update(array_map(fn ($value) => is_string($value) && trim($value) !== '' ? trim($value) : null, $data));

        if ($firm->wasChanged()) {
            Audit::log(AuditEvent::FirmUpdated, "Firma bilgileri güncellendi: {$firm->name}", $firm, ['changes' => AuditChanges::between($firm, $before, [
                'contact_name' => 'Yetkili kişi', 'phone' => 'Telefon', 'email' => 'E-posta', 'kep_address' => 'KEP adresi',
                'website' => 'Web sitesi', 'mersis_no' => 'MERSİS no', 'address' => 'Yazışma adresi',
            ])]);
        }

        Flux::toast(variant: 'success', text: 'Şirket bilgileri kaydedildi.');
    }

    private function savePayroll(): void
    {
        $this->authorize('update', $this->firm);

        $values = [];
        foreach (FirmSettings::PAYROLL as $key => [$label, $options]) {
            $value = $this->payroll[$key] ?? null;
            $values[$key] = $options === null ? (bool) $value : (in_array($value, $options, true) ? $value : FirmSettings::PAYROLL[$key][2]);
        }

        $this->storeSettings('payroll', $values, 'Bordro varsayılanları güncellendi');
        $this->payroll = $values;
        Flux::toast(variant: 'success', text: 'Bordro varsayılanları kaydedildi.');
    }

    private function saveSecurity(): void
    {
        $this->authorize('update', $this->firm);

        $this->storeSettings('security', ['require_two_factor' => $this->requireTwoFactor],
            $this->requireTwoFactor ? 'İki adımlı doğrulama firma için zorunlu yapıldı' : 'İki adımlı doğrulama zorunluluğu kaldırıldı');
        Flux::toast(variant: 'success', text: 'Güvenlik ayarları kaydedildi.');
    }

    private function savePreferences(): void
    {
        $preferences = [];
        foreach (NotificationCategory::configurable() as $category) {
            $preferences[$category->value] = [
                'mail' => (bool) ($this->prefs[$category->value]['mail'] ?? true),
                'panel' => (bool) ($this->prefs[$category->value]['panel'] ?? true),
            ];
        }

        Auth::user()->forceFill(['notification_preferences' => $preferences])->save();
        Flux::toast(variant: 'success', text: 'Bildirim tercihleri kaydedildi.');
    }

    private function saveProfile(): void
    {
        $user = Auth::user();
        $user->fill($this->validate($this->profileRules($user->id)));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
        Flux::toast(variant: 'success', text: 'Bilgileriniz kaydedildi.');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function storeSettings(string $group, array $values, string $description): void
    {
        $firm = $this->firm;
        $settings = $firm->settings ?? [];
        $before = $settings[$group] ?? [];
        $settings[$group] = $values;
        $firm->forceFill(['settings' => $settings])->save();

        if ($before != $values) {
            Audit::log(AuditEvent::FirmUpdated, "{$description}: {$firm->name}", $firm, ['settings' => [$group => $values]]);
        }
    }
}; ?>

<div>
    @php
        [$title, $description, $firmLevel] = self::TABS[$tab];
        $readOnly = $firmLevel && ! $this->canManage;
        $savable = in_array($tab, ['sirket', 'bordro', 'bildirim', 'guvenlik', 'tercih'], true) && ! $readOnly;
    @endphp

    <x-panel.page-header :crumbs="['Yönetim' => null, 'Ayarlar' => null, $this->firm->name => null]" title="Ayarlar"
        subtitle="Şirket, bordro, bildirim ve güvenlik tercihleri.">
        @if ($savable)
            <x-slot:actions>
                <flux:button variant="primary" icon="check" wire:click="save" data-test="save-settings">Değişiklikleri Kaydet</flux:button>
            </x-slot:actions>
        @endif
    </x-panel.page-header>

    <div class="grid items-start gap-5 lg:grid-cols-[240px_minmax(0,1fr)]">
        <nav class="rounded-2xl border border-line bg-white p-2" aria-label="Ayar bölümleri">
            @foreach (self::TABS as $slug => [$label])
                @if ($slug === 'onay')
                    <span class="flex w-full items-center gap-2 rounded-[9px] px-3 py-2.5 text-[13.5px] font-semibold text-faint" title="Bordro modülüyle birlikte gelecek">
                        <span class="flex-1">{{ $label }}</span>
                        <span class="rounded-md border border-line-2 px-1.5 text-[9.5px] font-bold tracking-wide">YAKINDA</span>
                    </span>
                @else
                    <button type="button" wire:click="$set('tab', '{{ $slug }}')" @class([
                        'flex w-full items-center rounded-[9px] px-3 py-2.5 text-start text-[13.5px] transition',
                        'bg-[#EEF3F9] font-extrabold text-ink shadow-[inset_3px_0_0_0_var(--color-mint)]' => $tab === $slug,
                        'font-semibold text-ink-2 hover:bg-row-hover' => $tab !== $slug,
                    ])>{{ $label }}</button>
                @endif
            @endforeach
        </nav>

        <div class="min-w-0 rounded-2xl border border-line bg-white shadow-[0_1px_3px_rgba(16,40,72,0.04)]">
            <div class="border-b border-line-3 px-6 py-5">
                <h2 class="text-[17px] font-extrabold tracking-tight text-ink">{{ $title }}</h2>
                <p class="mt-1 text-[13px] text-muted">{{ $description }}</p>
                @if ($readOnly)
                    <x-panel.alert class="mt-3">Bu bölümü yalnızca firma bilgilerini düzenleme yetkisi olan kullanıcılar değiştirebilir.</x-panel.alert>
                @endif
            </div>

            <div class="p-6">
                @if ($tab === 'sirket')
                    <div class="grid gap-x-5 gap-y-4 md:grid-cols-2">
                        <flux:input label="Ticari unvan" :value="$this->firm->title ?? $this->firm->name" disabled description="HRD tarafından yönetilir." />
                        <flux:input label="Vergi kimlik no" :value="$this->firm->tax_number" disabled />
                        <flux:input wire:model="firmForm.contact_name" label="Yetkili kişi" :disabled="$readOnly" />
                        <flux:input wire:model="firmForm.phone" label="Telefon" :disabled="$readOnly" />
                        <flux:input wire:model="firmForm.email" label="E-posta" type="email" :disabled="$readOnly" />
                        <flux:input wire:model="firmForm.kep_address" label="KEP adresi" placeholder="firma@hs01.kep.tr" :disabled="$readOnly" />
                        <flux:input wire:model="firmForm.website" label="Web sitesi" placeholder="firma.com.tr" :disabled="$readOnly" />
                        <flux:input wire:model="firmForm.mersis_no" label="MERSİS no" maxlength="16" :disabled="$readOnly" />
                        <div class="md:col-span-2">
                            <flux:textarea wire:model="firmForm.address" label="Yazışma adresi" rows="2" :disabled="$readOnly" />
                        </div>
                    </div>
                @elseif ($tab === 'bordro')
                    <div class="grid gap-x-5 gap-y-5 md:grid-cols-2">
                        @foreach (FirmSettings::PAYROLL as $key => [$label, $options])
                            @if ($options === null)
                                <div class="rounded-xl border border-line px-4 py-3">
                                    <flux:switch wire:model="payroll.{{ $key }}" :label="$label" :description="FirmSettings::PAYROLL_HINTS[$key] ?? null" :disabled="$readOnly" />
                                </div>
                            @else
                                <flux:select wire:model="payroll.{{ $key }}" :label="$label" :disabled="$readOnly">
                                    @foreach ($options as $option)
                                        <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @endif
                        @endforeach
                    </div>
                    <p class="mt-5 text-[12.5px] text-muted">Ücret tipi, asgari ücret istisnası ve otomatik BES yeni personel formunu önceden doldurur; diğerleri bordro hesaplamasında kullanılacak.</p>
                @elseif ($tab === 'bildirim')
                    <div class="overflow-hidden rounded-xl border border-line">
                        <div class="grid grid-cols-[minmax(0,1fr)_80px_80px] gap-3 bg-[#F7F9FC] px-4 py-2.5 text-[11.5px] font-bold tracking-wide text-muted-2">
                            <span>OLAY</span><span class="text-center">E-POSTA</span><span class="text-center">PANEL</span>
                        </div>
                        @foreach (NotificationCategory::configurable() as $category)
                            <div class="grid grid-cols-[minmax(0,1fr)_80px_80px] items-center gap-3 border-t border-line-4 px-4 py-3.5" wire:key="nc-{{ $category->value }}">
                                <div>
                                    <div class="text-[13.5px] font-bold text-ink">{{ $category->label() }}</div>
                                    <div class="text-[12px] text-muted">{{ $category->description() }}</div>
                                </div>
                                <div class="flex justify-center"><flux:switch wire:model="prefs.{{ $category->value }}.mail" aria-label="{{ $category->label() }} e-posta" /></div>
                                <div class="flex justify-center"><flux:switch wire:model="prefs.{{ $category->value }}.panel" aria-label="{{ $category->label() }} panel" /></div>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-4 text-[12.5px] text-muted">
                        KVKK başvuru yanıtları gibi zorunlu bilgilendirmeler her zaman gönderilir.
                        @unless (HrdNotification::mailEnabled())
                            E-posta gönderimi henüz yapılandırılmadı; e-posta tercihleri SMTP ayarlandığında geçerli olur.
                        @endunless
                    </p>
                @elseif ($tab === 'guvenlik')
                    <div class="flex flex-col gap-5">
                        <div>
                            <div class="mb-2.5 text-[12px] font-bold tracking-[0.04em] text-muted">FİRMA KURALLARI</div>
                            <div class="flex flex-col gap-3">
                                <div class="rounded-xl border border-line px-4 py-3">
                                    <flux:switch wire:model="requireTwoFactor" label="İki adımlı doğrulama zorunlu"
                                        description="Firmada çalışan tüm kullanıcılar panele devam etmeden önce iki adımlı doğrulamayı etkinleştirir." :disabled="! $this->canManage" />
                                </div>
                                <div class="rounded-xl border border-line px-4 py-3">
                                    <flux:switch :checked="true" disabled label="Hassas veri maskeleme" description="TCKN, IBAN ve hesap numarası her zaman maskeli gösterilir; açmak yetki ister ve işlem geçmişine yazılır." />
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="mb-2.5 text-[12px] font-bold tracking-[0.04em] text-muted">HESABINIZ</div>
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line px-4 py-3.5">
                                <div class="text-[13.5px]">
                                    <span class="font-bold text-ink">İki adımlı doğrulama</span>
                                    @if (auth()->user()->two_factor_confirmed_at)
                                        <x-panel.badge color="green" class="ms-2">Açık</x-panel.badge>
                                    @else
                                        <x-panel.badge color="amber" class="ms-2">Kapalı</x-panel.badge>
                                    @endif
                                    <div class="mt-0.5 text-[12px] text-muted">Şifre değiştirme, iki adımlı doğrulama ve geçiş anahtarları.</div>
                                </div>
                                <flux:button icon="shield-check" :href="route('security.edit')" wire:navigate>Güvenlik ayarlarını aç</flux:button>
                            </div>
                        </div>
                    </div>
                @elseif ($tab === 'marka')
                    <div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                        <div>
                            <div class="flex h-[150px] items-center justify-center rounded-xl border-[1.5px] border-dashed border-line-2 bg-[#F7F9FC] p-4">
                                @if ($this->firm->logo_path)
                                    <img src="{{ route('firms.logo', [$this->firm, 'v' => $this->firm->updated_at?->timestamp]) }}" alt="{{ $this->firm->name }} logosu" class="max-h-[110px] max-w-[80%] object-contain" data-test="firm-logo">
                                @else
                                    <span class="text-[13px] text-muted">Logo yüklenmedi</span>
                                @endif
                            </div>
                            @if ($this->canManage)
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <flux:input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" size="sm" class="max-w-xs" aria-label="Logo" />
                                    @if ($this->firm->logo_path)
                                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeLogo" wire:confirm="Logo kaldırılsın mı?">Kaldır</flux:button>
                                    @endif
                                </div>
                                <flux:error name="logo" />
                                <p class="mt-2 text-[12px] text-muted">PNG, JPG veya WEBP; en fazla 1 MB. Şeffaf arka planlı yatay logo önerilir.</p>
                            @endif
                        </div>
                        <div>
                            <div class="mb-2 text-[12px] font-bold tracking-[0.04em] text-muted">ÖNİZLEME · PDF ÜST BİLGİ</div>
                            <div class="rounded-xl border border-line p-4">
                                <div class="flex items-center justify-between border-b border-line-3 pb-3">
                                    @if ($this->firm->logo_path)
                                        <img src="{{ route('firms.logo', [$this->firm, 'v' => $this->firm->updated_at?->timestamp]) }}" alt="" class="h-[30px] max-w-[110px] object-contain">
                                    @else
                                        <span class="text-[13px] font-extrabold text-ink">{{ $this->firm->name }}</span>
                                    @endif
                                    <span class="text-[11px] font-bold text-muted-2">ÜCRET BORDROSU</span>
                                </div>
                                <div class="space-y-1.5 py-4">
                                    <div class="h-2 w-3/4 rounded bg-line-3"></div>
                                    <div class="h-2 w-1/2 rounded bg-line-3"></div>
                                    <div class="h-2 w-2/3 rounded bg-line-3"></div>
                                </div>
                                <div class="border-t border-line-3 pt-2.5 text-[10.5px] text-muted-2">
                                    {{ $this->firm->name }}{{ $this->firm->tax_number ? ' · VKN '.$this->firm->tax_number : '' }} · Bu belge HRD Bordro ile oluşturulmuştur.
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif ($tab === 'tercih')
                    <div class="grid gap-x-5 gap-y-4 md:grid-cols-2">
                        <flux:input wire:model="name" label="Ad soyad" required />
                        <flux:input wire:model="email" label="E-posta" type="email" required description="Değiştirirseniz yeni adresi doğrulamanız istenir." />
                        <div class="md:col-span-2">
                            <div class="mb-2 text-sm font-medium text-ink-2">Görünüm</div>
                            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" class="max-w-md">
                                <flux:radio value="light" icon="sun">Açık</flux:radio>
                                <flux:radio value="dark" icon="moon">Koyu</flux:radio>
                                <flux:radio value="system" icon="computer-desktop">Sistem</flux:radio>
                            </flux:radio.group>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-2 border-t border-line-3 pt-5">
                        <flux:button size="sm" icon="shield-check" :href="route('security.edit')" wire:navigate>Şifre ve iki adımlı doğrulama</flux:button>
                        <flux:button size="sm" icon="document-text" :href="route('kvkk.edit')" wire:navigate>KVKK onaylarım ve başvurularım</flux:button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
