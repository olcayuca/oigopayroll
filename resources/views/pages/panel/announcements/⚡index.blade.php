<?php

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Duyurular (prototype 34-duyurular): announcements meant for the user, by category; pinned first,
 * unread marked. The top strip and notifications open it focused on one announcement (?duyuru=).
 */
new #[Title('Duyurular')] class extends Component {
    #[Url(as: 'kategori', except: '')]
    public string $category = '';

    /** guncel | arsiv */
    #[Url(as: 'gorunum', except: 'guncel')]
    public string $view = 'guncel';

    #[Url(as: 'duyuru', except: null)]
    public ?int $focus = null;

    public function mount(): void
    {
        if ($this->focus !== null) {
            $this->read($this->focus);
        }
    }

    /**
     * @return Collection<int, Announcement>
     */
    #[Computed]
    public function all(): Collection
    {
        return Announcement::query()->forAudience(Auth::user())
            ->when($this->view !== 'arsiv', fn ($query) => $query->live())
            ->when($this->view === 'arsiv', fn ($query) => $query->whereNotNull('ends_at')->where('ends_at', '<=', now())->where('ends_at', '>', now()->subYear()))
            ->orderByDesc('pinned')->latest('starts_at')
            ->get();
    }

    /**
     * @return Collection<int, Announcement>
     */
    #[Computed]
    public function announcements(): Collection
    {
        return $this->category === '' ? $this->all : $this->all->where('category', $this->category)->values();
    }

    /**
     * Ids of the listed announcements the user has read.
     *
     * @return list<int>
     */
    #[Computed]
    public function readIds(): array
    {
        return DB::table('announcement_reads')->where('user_id', Auth::id())->whereIn('announcement_id', $this->all->modelKeys())
            ->pluck('announcement_id')->map(fn ($id) => (int) $id)->values()->all();
    }

    public function read(int $id): void
    {
        $announcement = Announcement::query()->forAudience(Auth::user())->find($id);

        if ($announcement === null) {
            $this->focus = null;

            return;
        }

        $announcement->markReadBy(Auth::user());
        $this->focus = $id;
        unset($this->readIds);
    }

    public function readAll(): void
    {
        foreach ($this->all as $announcement) {
            $announcement->markReadBy(Auth::user());
        }

        unset($this->readIds);
    }

    /**
     * Hide from the top strip (still listed here). Critical ones stay until they end.
     */
    public function dismiss(int $id): void
    {
        $announcement = $this->all->firstWhere('id', $id);

        if ($announcement?->isDismissible()) {
            $announcement->dismissedBy()->syncWithoutDetaching([Auth::id() => ['dismissed_at' => now()]]);
        }
    }
}; ?>

<div>
    @php $unread = $this->all->whereNotIn('id', $this->readIds)->count(); @endphp

    <x-panel.page-header :crumbs="['Yönetim' => null, 'Duyurular' => null]" title="Duyurular"
        subtitle="HRD'den bakım, mevzuat ve yeni özellik duyuruları.">
        <x-slot:actions>
            @if ($unread > 0)
                <flux:button icon="check" wire:click="readAll" data-test="read-all">Tümünü okundu say ({{ $unread }})</flux:button>
            @endif
        </x-slot:actions>
    </x-panel.page-header>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-1 rounded-[10px] bg-seg p-[3px]" role="group" aria-label="Kategori">
            @foreach (['' => 'Tümü', ...array_combine(array_keys(Announcement::CATEGORIES), array_keys(Announcement::CATEGORIES))] as $value => $label)
                @php $count = $value === '' ? $this->all->count() : $this->all->where('category', $value)->count(); @endphp
                <button type="button" wire:click="$set('category', '{{ $value }}')" @class([
                    'h-[34px] rounded-lg px-3.5 text-[13px] font-bold transition',
                    'bg-white text-ink shadow-[0_1px_3px_rgba(16,40,72,0.1)]' => $category === $value,
                    'text-muted hover:text-ink' => $category !== $value,
                ])>{{ $label }} <span class="ms-1 text-[11.5px] text-muted-2 tabular-nums">{{ $count }}</span></button>
            @endforeach
        </div>
        <x-panel.segmented model="view" :current="$view" :options="['guncel' => 'Güncel', 'arsiv' => 'Arşiv']" />
    </div>

    <div class="flex flex-col gap-3.5">
        @forelse ($this->announcements as $announcement)
            @php
                $isUnread = ! in_array($announcement->id, $this->readIds, true);
                $focused = $focus === $announcement->id;
            @endphp
            <article wire:key="a-{{ $announcement->id }}" wire:click="read({{ $announcement->id }})" data-test="announcement-card"
                @class([
                    'cursor-pointer rounded-2xl border-[1.5px] bg-white px-5 py-[18px] transition',
                    'border-mint shadow-[0_0_0_4px_rgba(17,167,149,0.1)]' => $focused,
                    'border-line shadow-[0_1px_3px_rgba(16,40,72,0.04)] hover:border-line-2' => ! $focused,
                    'border-s-4 !border-s-st-blue-dot' => $isUnread,
                ])
                x-data x-init="{{ $focused ? '$el.scrollIntoView({ block: \'center\' })' : '' }}">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <x-panel.badge :color="Announcement::CATEGORIES[$announcement->category] ?? 'blue'" :dot="false" class="!text-[11px]">{{ $announcement->category }}</x-panel.badge>
                    @if ($announcement->level === 'critical')
                        <x-panel.badge color="red" class="!text-[11px]">Kritik</x-panel.badge>
                    @endif
                    @if ($announcement->pinned)
                        <span class="inline-flex items-center gap-1 text-[11.5px] font-bold text-mint-strong"><flux:icon.bookmark variant="micro" class="size-3.5" /> Sabitlendi</span>
                    @endif
                    <span class="text-[12px] font-semibold text-faint">{{ $announcement->starts_at->translatedFormat('d M Y') }}</span>
                    @if ($isUnread)
                        <span class="ms-auto rounded-md bg-st-blue-bg px-2 py-0.5 text-[10.5px] font-extrabold tracking-wide text-st-blue">YENİ</span>
                    @endif
                </div>
                <h2 class="text-[15.5px] font-extrabold text-ink">{{ $announcement->title }}</h2>
                <x-policy-body :document="$announcement" class="mt-1 !text-[13.5px] !text-ink-2 [&_p]:mt-1" />
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    @if ($href = $announcement->linkHref())
                        <a href="{{ $href }}" wire:click.stop @if (str_starts_with((string) $announcement->link_url, 'http')) target="_blank" rel="noopener" @else wire:navigate @endif
                            class="inline-flex items-center gap-1 text-[13px] font-bold text-brand hover:text-mint">{{ $announcement->link_label }} <flux:icon.arrow-right variant="micro" class="size-3.5" /></a>
                    @endif
                    @if ($view !== 'arsiv' && $announcement->isDismissible())
                        <button type="button" wire:click.stop="dismiss({{ $announcement->id }})" class="ms-auto text-[12px] font-semibold text-muted hover:text-brand">Üst şeritte gösterme</button>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-line bg-white">
                <x-panel.empty icon="megaphone" :title="$view === 'arsiv' ? 'Arşivde duyuru yok' : 'Duyuru yok'">
                    {{ $view === 'arsiv' ? 'Son bir yılda süresi biten duyuru bulunmuyor.' : 'Yeni bir duyuru yayınlandığında burada ve üst şeritte görünür.' }}
                </x-panel.empty>
            </div>
        @endforelse
    </div>
</div>
