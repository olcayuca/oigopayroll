<?php

use App\Enums\AuditEvent;
use App\Enums\FirmStatus;
use App\Models\Announcement;
use App\Models\Firm;
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
        return Announcement::query()->with(['firms', 'creator'])->withCount('dismissedBy')
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
        $this->reset('editingId', 'title', 'body', 'firmIds', 'endsAt');
        $this->level = 'info';
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
            'audience' => ['required', Rule::in(array_keys(Announcement::AUDIENCES))],
            'firmIds' => [Rule::requiredIf($this->audience === 'firms'), 'array'],
            'firmIds.*' => ['integer', Rule::exists('firms', 'id')],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['nullable', 'date', 'after:startsAt'],
        ], ['firmIds.required' => 'En az bir firma seçin.'], [
            'title' => 'Başlık', 'body' => 'Metin', 'level' => 'Düzey', 'audience' => 'Hedef', 'startsAt' => 'Başlangıç', 'endsAt' => 'Bitiş',
        ]);

        $announcement = $this->editingId ? Announcement::findOrFail($this->editingId) : new Announcement(['created_by' => auth()->id()]);
        $announcement->fill([
            'title' => $data['title'],
            'body' => $data['body'],
            'level' => $data['level'],
            'audience' => $data['audience'],
            'starts_at' => Carbon::parse($data['startsAt']),
            'ends_at' => $data['endsAt'] ? Carbon::parse($data['endsAt']) : null,
        ])->save();

        $announcement->firms()->sync($data['audience'] === 'firms' ? array_map('intval', $data['firmIds']) : []);

        Audit::log(AuditEvent::AnnouncementSaved, "Duyuru kaydedildi: {$announcement->title}", $announcement);

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
            <flux:text class="mt-1">Panelin üst kısmında gösterilen duyurular (bakım, mevzuat değişikliği, bordro takvimi…). Kritik duyurular kapatılamaz.</flux:text>
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
                            <flux:heading>{{ $announcement->title }}</flux:heading>
                        </div>
                        <flux:text size="sm" class="mt-1">
                            {{ $announcement->starts_at->format('d.m.Y H:i') }} – {{ $announcement->ends_at?->format('d.m.Y H:i') ?? 'süresiz' }}
                            · {{ $announcement->audience === 'firms' ? $announcement->firms->pluck('name')->implode(', ') : Announcement::AUDIENCES[$announcement->audience] }}
                            · {{ $announcement->getAttribute('dismissed_by_count') }} kişi kapattı
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
                <flux:select wire:model="level" label="Düzey">
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
                <flux:input type="datetime-local" wire:model="startsAt" label="Başlangıç" required />
                <flux:input type="datetime-local" wire:model="endsAt" label="Bitiş" description="Boşsa siz kaldırana kadar." />
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
