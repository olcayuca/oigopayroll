<?php

use App\Enums\AuditEvent;
use App\Support\Audit;
use App\Enums\Portal;
use App\Support\LandingContent;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Web Sitesi')] class extends Component {
    /** @var array<string, mixed> */
    public array $content = [];

    #[Url(as: 'sekme', except: 'giris')]
    public string $tab = 'giris';

    public function mount(): void
    {
        $this->authorize('manage-settings');

        $this->content = LandingContent::get();
    }

    public function addService(): void
    {
        if (count($this->content['services']) < LandingContent::MAX_SERVICES) {
            $this->content['services'][] = ['title' => '', 'text' => ''];
        }
    }

    public function removeService(int $index): void
    {
        unset($this->content['services'][$index]);
        $this->content['services'] = array_values($this->content['services']);
    }

    public function moveService(int $index, int $direction): void
    {
        $target = $index + $direction;
        $services = $this->content['services'];

        if (isset($services[$index], $services[$target])) {
            [$services[$index], $services[$target]] = [$services[$target], $services[$index]];
            $this->content['services'] = $services;
        }
    }

    public function save(): void
    {
        $this->authorize('manage-settings');

        try {
            $data = $this->validateContent();
        } catch (ValidationException $e) {
            $this->tab = $this->tabsWithErrors(array_keys($e->errors()))[0] ?? $this->tab;

            throw $e;
        }

        LandingContent::save($data['content']);
        Audit::log(AuditEvent::WebsiteChanged, 'Web sitesi içeriği güncellendi');

        Flux::toast(variant: 'success', text: 'Web sitesi içeriği kaydedildi.');
    }

    /**
     * Form tabs whose fields appear in the given error keys (content.hero_title, content.services.2.title, ...).
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public function tabsWithErrors(array $keys): array
    {
        $prefixes = [
            'giris' => ['content.hero_'],
            'hizmetler' => ['content.services'],
            'hakkimizda' => ['content.about_'],
            'seo' => ['content.meta_'],
        ];

        return array_keys(array_filter($prefixes, fn ($tabPrefixes) => collect($keys)
            ->contains(fn ($key) => collect($tabPrefixes)->contains(fn ($prefix) => str_starts_with($key, $prefix)))));
    }

    /**
     * @return array{content: array<string, mixed>}
     */
    private function validateContent(): array
    {
        return $this->validate([
            'content.hero_title' => ['required', 'string', 'max:120'],
            'content.hero_subtitle' => ['nullable', 'string', 'max:300'],
            'content.hero_cta' => ['required', 'string', 'max:40'],
            'content.services_title' => ['required', 'string', 'max:120'],
            'content.services' => ['array', 'max:'.LandingContent::MAX_SERVICES],
            'content.services.*.title' => ['required', 'string', 'max:80'],
            'content.services.*.text' => ['nullable', 'string', 'max:300'],
            'content.about_title' => ['required', 'string', 'max:120'],
            'content.about_text' => ['nullable', 'string', 'max:3000'],
            'content.meta_title' => ['nullable', 'string', 'max:70'],
            'content.meta_description' => ['nullable', 'string', 'max:160'],
        ], [], [
            'content.hero_title' => 'Ana başlık',
            'content.hero_subtitle' => 'Alt başlık',
            'content.hero_cta' => 'Buton metni',
            'content.services_title' => 'Hizmetler başlığı',
            'content.services.*.title' => 'Hizmet başlığı',
            'content.services.*.text' => 'Hizmet açıklaması',
            'content.about_title' => 'Hakkımızda başlığı',
            'content.about_text' => 'Hakkımızda metni',
            'content.meta_title' => 'SEO başlığı',
            'content.meta_description' => 'SEO açıklaması',
        ]);
    }

    public function landingUrl(): string
    {
        return Portal::Landing->url('/');
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Web Sitesi</flux:heading>
            <flux:text class="mt-1">Tanıtım sayfasının (landing) içeriği. İletişim bilgileri Sistem Ayarları → Genel'den gelir.</flux:text>
        </div>
        <flux:button icon="arrow-top-right-on-square" :href="$this->landingUrl()" target="_blank">Siteyi Görüntüle</flux:button>
    </div>

    <form wire:submit="save" class="max-w-3xl space-y-6">
        <x-tabs :active="$tab" :invalid="$this->tabsWithErrors($errors->keys())"
            :tabs="['giris' => 'Giriş Bölümü', 'hizmetler' => 'Hizmetler', 'hakkimizda' => 'Hakkımızda', 'seo' => 'Arama Motoru (SEO)']"
            :counts="['hizmetler' => count($content['services'])]" />

        @if ($tab === 'giris')
        <section class="space-y-4">
            <flux:input wire:model="content.hero_title" label="Ana başlık" required />
            <flux:textarea wire:model="content.hero_subtitle" label="Alt başlık" rows="2" />
            <flux:input wire:model="content.hero_cta" label="Buton metni" description="Buton, müşteri paneli başvuru sayfasına gider." required />
        </section>
        @endif

        @if ($tab === 'hizmetler')
        <section class="space-y-4">
            <div class="flex items-center justify-end">
                @if (count($content['services']) < \App\Support\LandingContent::MAX_SERVICES)
                    <flux:button size="sm" icon="plus" wire:click="addService">Hizmet Ekle</flux:button>
                @endif
            </div>
            <flux:input wire:model="content.services_title" label="Bölüm başlığı" required />

            @foreach ($content['services'] as $index => $service)
                <div wire:key="service-{{ $index }}" class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <flux:text class="font-medium">Hizmet {{ $index + 1 }}</flux:text>
                        <div class="flex gap-1">
                            <flux:button size="xs" variant="ghost" icon="chevron-up" wire:click="moveService({{ $index }}, -1)" :disabled="$index === 0" />
                            <flux:button size="xs" variant="ghost" icon="chevron-down" wire:click="moveService({{ $index }}, 1)" :disabled="$loop->last" />
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeService({{ $index }})" />
                        </div>
                    </div>
                    <flux:input wire:model="content.services.{{ $index }}.title" label="Başlık" required />
                    <flux:textarea wire:model="content.services.{{ $index }}.text" label="Açıklama" rows="2" />
                </div>
            @endforeach
        </section>
        @endif

        @if ($tab === 'hakkimizda')
        <section class="space-y-4">
            <flux:input wire:model="content.about_title" label="Başlık" required />
            <flux:textarea wire:model="content.about_text" label="Metin" rows="5" />
        </section>
        @endif

        @if ($tab === 'seo')
        <section class="space-y-4">
            <flux:input wire:model="content.meta_title" label="Sayfa başlığı" description="Boşsa site adı kullanılır. En fazla 70 karakter." />
            <flux:textarea wire:model="content.meta_description" label="Açıklama" rows="2" description="En fazla 160 karakter." />
        </section>
        @endif

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Kaydet</flux:button>
        </div>
    </form>
</div>
