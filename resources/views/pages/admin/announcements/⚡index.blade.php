<?php

use App\Enums\AuditEvent;
use App\Enums\FirmStatus;
use App\Models\Announcement;
use App\Models\Firm;
use App\Notifications\DeliverAnnouncements;
use App\Support\Audit;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Duyurular')] class extends Component {
    #[Url(as: 'sekme')]
    public string $tab = 'yayinda';

    public ?int $editingId = null;

    public string $title = '';

    public string $body = '';

    public string $level = 'info';

    public string $category = 'Sistem';

    public bool $pinned = false;

    public string $linkLabel = '';

    public string $linkUrl = '';

    public bool $notify = true;

    public string $audience = 'all';

    /** @var list<string> */
    public array $firmIds = [];

    public string $startsAt = '';

    public string $endsAt = '';

    public function mount(): void
    {
        $this->authorize('manage-settings');
    }

    /**
     * @return Collection<int, Announcement>
     */
    #[Computed]
    public function announcements(): Collection
    {
        return Announcement::query()->with(['firms', 'creator'])->withCount(['dismissedBy', 'readers'])
            ->when($this->tab === 'yayinda', fn ($query) => $query->live())
            ->when($this->tab === 'planli', fn ($query) => $query->where('starts_at', '>', now()))
            ->when($this->tab === 'biten', fn ($query) => $query->whereNotNull('ends_at')->where('ends_at', '<=', now()))
            ->latest('starts_at')
            ->get();
    }

    /**
     * @return Collection<int, Firm>
     */
    #[Computed]
    public function firms(): Collection
    {
        return Firm::query()->whereIn('status', [FirmStatus::Active, FirmStatus::Pending])->orderBy('name')->get(['id', 'name']);
    }

    public function create(): void
    {
        $this->reset('editingId', 'title', 'body', 'firmIds', 'endsAt', 'pinned', 'linkLabel', 'linkUrl');
        $this->level = 'info';
        $this->category = 'Sistem';
        $this->notify = true;
        $this->audience = 'all';
        $this->startsAt = now()->format('Y-m-d\TH:i');
        $this->resetValidation();

        Flux::modal('announcement')->show();
    }

    public function edit(int $id): void
    {
        $announcement = Announcement::with('firms')->findOrFail($id);

        $this->editingId = $announcement->id;
        $this->title = $announcement->title;
        $this->body = $announcement->body;
        $this->level = $announcement->level;
        $this->category = $announcement->category;
        $this->pinned = $announcement->pinned;
        $this->linkLabel = (string) $announcement->link_label;
        $this->linkUrl = (string) $announcement->link_url;
        $this->notify = $announcement->notify;
        $this->audience = $announcement->audience;
        $this->firmIds = $announcement->firms->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->startsAt = $announcement->starts_at->format('Y-m-d\TH:i');
        $this->endsAt = (string) $announcement->ends_at?->format('Y-m-d\TH:i');
        $this->resetValidation();

        Flux::modal('announcement')->show();
    }

    public function save(): void
    {
        $this->authorize('manage-settings');

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'level' => ['required', Rule::in(array_keys(Announcement::LEVELS))],
            'category' => ['required', Rule::in(array_keys(Announcement::CATEGORIES))],
            'pinned' => ['boolean'],
            'notify' => ['boolean'],
            'linkLabel' => ['nullable', 'required_with:linkUrl', 'string', 'max:60'],
            'linkUrl' => ['nullable', 'required_with:linkLabel', 'string', 'max:255', 'regex:#^(/[^\s]*|https://[^\s]+)$#'],
            'audience' => ['required', Rule::in(array_keys(Announcement::AUDIENCES))],
            'firmIds' => [Rule::requiredIf($this->audience === 'firms'), 'array'],
            'firmIds.*' => ['integer', Rule::exists('firms', 'id')],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['nullable', 'date', 'after:startsAt'],
        ], ['firmIds.required' => 'En az bir firma seçin.', 'linkUrl.regex' => 'Panel içi yol (/personel) ya da https:// ile başlayan adres girin.'], [
            'title' => 'Başlık', 'body' => 'Metin', 'level' => 'Düzey', 'category' => 'Kategori', 'linkLabel' => 'Bağlantı metni', 'linkUrl' => 'Bağlantı adresi', 'audience' => 'Hedef', 'startsAt' => 'Başlangıç', 'endsAt' => 'Bitiş',
        ]);

        $announcement = $this->editingId ? Announcement::findOrFail($this->editingId) : new Announcement(['created_by' => auth()->id()]);
        $announcement->fill([
            'title' => $data['title'],
            'body' => $data['body'],
            'level' => $data['level'],
            'category' => $data['category'],
            'pinned' => $data['pinned'],
            'link_label' => $data['linkLabel'] ?: null,
            'link_url' => $data['linkUrl'] ?: null,
            'notify' => $data['notify'],
            'audience' => $data['audience'],
            'starts_at' => Carbon::parse($data['startsAt']),
            'ends_at' => $data['endsAt'] ? Carbon::parse($data['endsAt']) : null,
        ])->save();

        $announcement->firms()->sync($data['audience'] === 'firms' ? array_map('intval', $data['firmIds']) : []);

        Audit::log(AuditEvent::AnnouncementSaved, "Duyuru kaydedildi: {$announcement->title}", $announcement);

        // Already live: announce now; planned ones are announced by the scheduler when they start.
        app(DeliverAnnouncements::class)->run();

        unset($this->announcements);
        Flux::modal('announcement')->close();
        Flux::toast(variant: 'success', text: 'Duyuru kaydedildi.');
    }

    /**
     * End an announcement now (kept for the record).
     */
    public function end(int $id): void
    {
        $this->authorize('manage-settings');

        $announcement = Announcement::findOrFail($id);
        $announcement->update(['ends_at' => now()]);
        Audit::log(AuditEvent::AnnouncementSaved, "Duyuru yayından kaldırıldı: {$announcement->title}", $announcement);

        unset($this->announcements);
        Flux::toast(variant: 'success', text: 'Duyuru yayından kaldırıldı.');
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Duyurular</flux:heading>
            <flux:text class="mt-1">Panelin üst şeridinde ve Duyurular sayfasında gösterilir (bakım, mevzuat, yeni özellik…). İsterseniz yayına girince bildirim ve e-posta gider; kritik duyurular kapatılamaz.</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">Yeni Duyuru</flux:button>
    </div>

    <x-tabs :active="$tab" :tabs="['yayinda' => 'Yayında', 'planli' => 'Planlı', 'biten' => 'Biten', 'tumu' => 'Tümü']" />

    <div class="space-y-3">
        @forelse ($this->announcements as $announcement)
            <flux:card class="space-y-2" wire:key="announcement-{{ $announcement->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" :color="['info' => 'sky', 'warning' => 'amber', 'critical' => 'red'][$announcement->level]">{{ Announcement::LEVELS[$announcement->level] }}</flux:badge>
                            <flux:badge size="sm" :color="Announcement::CATEGORIES[$announcement->category] ?? 'zinc'">{{ $announcement->category }}</flux:badge>
                            @if ($announcement->pinned) <flux:icon.bookmark variant="micro" class="size-4 text-teal-600" /> @endif
                            <flux:heading>{{ $announcement->title }}</flux:heading>
                        </div>
                        <flux:text size="sm" class="mt-1">
                            {{ $announcement->starts_at->format('d.m.Y H:i') }} – {{ $announcement->ends_at?->format('d.m.Y H:i') ?? 'süresiz' }}
                            · {{ $announcement->audience === 'firms' ? $announcement->firms->pluck('name')->implode(', ') : Announcement::AUDIENCES[$announcement->audience] }}
                            · {{ $announcement->getAttribute('readers_count') }} kişi okudu · {{ $announcement->getAttribute('dismissed_by_count') }} kişi şeritten kaldırdı
                            · @if (! $announcement->notify) bildirim yok @elseif ($announcement->notified_at) bildirim gönderildi {{ $announcement->notified_at->format('d.m.Y H:i') }} @else yayınlanınca bildirilecek @endif
                        </flux:text>
                    </div>
                    <div class="flex gap-1">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $announcement->id }})" />
                        @if ($announcement->isLive())
                            <flux:button size="sm" variant="ghost" icon="stop" wire:click="end({{ $announcement->id }})" wire:confirm="Duyuru şimdi yayından kaldırılsın mı?">Yayından kaldır</flux:button>
                        @endif
                    </div>
                </div>
                <x-policy-body :document="$announcement" />
            </flux:card>
        @empty
            <flux:text class="py-10 text-center text-zinc-500">Duyuru yok.</flux:text>
        @endforelse
    </div>

    <flux:modal name="announcement" class="md:w-[40rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Duyuruyu Düzenle' : 'Yeni Duyuru' }}</flux:heading>
            <flux:input wire:model="title" label="Başlık" required />
            <flux:textarea wire:model="body" label="Metin" rows="5" description="Markdown desteklenir: **kalın**, - madde, [bağlantı](https://…)." required />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="category" label="Kategori">
                    @foreach (array_keys(Announcement::CATEGORIES) as $option)
                        <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="level" label="Düzey" description="Kritik: kapatılamaz, sayfanın üstünde de görünür.">
                    @foreach (Announcement::LEVELS as $key => $label)
                        <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="audience" label="Hedef">
                    @foreach (Announcement::AUDIENCES as $key => $label)
                        <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            @if ($audience === 'firms')
                <flux:checkbox.group wire:model="firmIds" label="Firmalar" class="max-h-48 overflow-y-auto">
                    @foreach ($this->firms as $firm)
                        <flux:checkbox value="{{ $firm->id }}" label="{{ $firm->name }}" />
                    @endforeach
                </flux:checkbox.group>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="linkLabel" label="Bağlantı metni" placeholder="Raporlara git" />
                <flux:input wire:model="linkUrl" label="Bağlantı adresi" placeholder="/personel ya da https://…" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="datetime-local" wire:model="startsAt" label="Başlangıç" required />
                <flux:input type="datetime-local" wire:model="endsAt" label="Bitiş" description="Boşsa siz kaldırana kadar." />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:switch wire:model="pinned" label="Sabitle" description="Duyurular sayfasında en üstte." />
                <flux:switch wire:model="notify" label="Bildirim gönder" description="Yayına girince hedef kullanıcılara bildirim ve e-posta." />
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
