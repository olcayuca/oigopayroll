<?php

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * Live announcements for the signed-in panel user.
 * "ticker": rotating strip in the top bar, opens a list with the full texts.
 * "banner": critical announcements above the page content (cannot be dismissed).
 */
new class extends Component {
    #[Locked]
    public string $mode = 'ticker';

    /**
     * @return Collection<int, Announcement>
     */
    #[Computed]
    public function announcements(): Collection
    {
        return Auth::user() ? Announcement::query()->for(Auth::user())->limit(5)->get() : new Collection;
    }

    public function color(string $level): string
    {
        return ['info' => 'blue', 'warning' => 'amber', 'critical' => 'red'][$level] ?? 'blue';
    }

    public function dismiss(int $id): void
    {
        $announcement = $this->announcements->firstWhere('id', $id);

        if ($announcement?->isDismissible()) {
            $announcement->dismissedBy()->syncWithoutDetaching([Auth::id() => ['dismissed_at' => now()]]);
        }

        unset($this->announcements);
    }
}; ?>

<div
    @class([
        'space-y-3' => $mode === 'banner',
        'mb-5' => $mode === 'banner' && $this->announcements->where('level', 'critical')->isNotEmpty(),
        'flex min-w-0 flex-1 justify-center' => $mode !== 'banner',
    ])
    x-data="{ index: 0, count: {{ $this->announcements->count() }}, paused: false, list: false }"
    x-init="setInterval(() => { if (! paused && count > 1) index = (index + 1) % count }, 6000)"
    x-on:keydown.escape.window="list = false"
>
    @if ($mode === 'banner')
        @foreach ($this->announcements->where('level', 'critical') as $announcement)
            <x-panel.alert variant="danger" :title="$announcement->title" icon="megaphone" wire:key="banner-{{ $announcement->id }}">
                <x-policy-body :document="$announcement" class="!text-inherit [&_p]:mt-0" />
            </x-panel.alert>
        @endforeach
    @elseif ($this->announcements->isNotEmpty())
            <button type="button" x-on:click="list = true" x-on:mouseenter="paused = true" x-on:mouseleave="paused = false" data-test="announcement-ticker"
                class="flex h-10 w-full max-w-[560px] min-w-0 items-center gap-2.5 rounded-[10px] border-[1.5px] border-[#E5EEF0] bg-[#F4FAF9] ps-2 pe-1.5 text-start transition hover:border-mint">
                <span class="flex size-[26px] shrink-0 items-center justify-center rounded-[7px] bg-mint text-white">
                    <flux:icon.megaphone variant="micro" class="size-3.5" />
                </span>
                @foreach ($this->announcements as $announcement)
                    <span x-show="index === {{ $loop->index }}" @if (! $loop->first) x-cloak @endif class="flex min-w-0 flex-1 items-center gap-2.5">
                        <x-panel.badge :color="$this->color($announcement->level)" :dot="false" class="hidden shrink-0 !text-[11px] xl:inline-flex">
                            {{ \App\Models\Announcement::LEVELS[$announcement->level] ?? '' }}
                        </x-panel.badge>
                        <span class="truncate text-[13px] font-bold text-ink">{{ $announcement->title }}</span>
                    </span>
                @endforeach
                @if ($this->announcements->count() > 1)
                    <span class="flex shrink-0 gap-1 px-1">
                        @foreach ($this->announcements as $announcement)
                            <span class="h-1.5 rounded-full transition-all" x-bind:class="index === {{ $loop->index }} ? 'w-4 bg-mint' : 'w-1.5 bg-[#CFE9E3]'"></span>
                        @endforeach
                    </span>
                @endif
            </button>

            {{-- Full list --}}
            <template x-teleport="body">
                <div x-cloak x-show="list" x-transition.opacity class="oigo-modal fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-[#0E2038]/45 p-4 pt-[10vh]" x-on:click.self="list = false">
                    <div class="w-full max-w-xl rounded-2xl bg-white shadow-[0_24px_60px_rgba(16,40,72,0.3)]" role="dialog" aria-modal="true" aria-label="Duyurular">
                        <div class="flex items-center justify-between border-b border-line-3 px-5 py-4">
                            <span class="text-base font-extrabold text-ink">Duyurular</span>
                            <button type="button" x-on:click="list = false" class="rounded-lg p-1 text-muted hover:bg-seg hover:text-ink" aria-label="Kapat">
                                <flux:icon.x-mark variant="mini" />
                            </button>
                        </div>
                        <div class="max-h-[65vh] divide-y divide-line-3 overflow-y-auto">
                            @foreach ($this->announcements as $announcement)
                                <div class="px-5 py-4" wire:key="announcement-{{ $announcement->id }}">
                                    <div class="mb-1.5 flex items-center gap-2">
                                        <x-panel.badge :color="$this->color($announcement->level)">{{ \App\Models\Announcement::LEVELS[$announcement->level] ?? '' }}</x-panel.badge>
                                        <span class="text-[11.5px] font-semibold text-faint">{{ $announcement->starts_at?->format('d.m.Y') }}</span>
                                        @if ($announcement->isDismissible())
                                            <button type="button" wire:click="dismiss({{ $announcement->id }})" class="ms-auto text-xs font-bold text-muted hover:text-brand" aria-label="Duyuruyu kapat">Bir daha gösterme</button>
                                        @endif
                                    </div>
                                    <div class="text-[14px] font-extrabold text-ink">{{ $announcement->title }}</div>
                                    <x-policy-body :document="$announcement" class="!text-[13px] !text-ink-2 [&_p]:mt-1" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </template>
    @endif
</div>
