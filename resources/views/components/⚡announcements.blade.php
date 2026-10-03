<?php

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * Live announcements for the signed-in panel user.
 * "ticker": rotating strip in the top bar; a click opens the Duyurular page on that announcement.
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
}; ?>

<div
    @class([
        'space-y-3' => $mode === 'banner',
        'mb-5' => $mode === 'banner' && $this->announcements->where('level', 'critical')->isNotEmpty(),
        'flex min-w-0 flex-1 justify-center' => $mode !== 'banner',
    ])
    x-data="{ index: 0, count: {{ $this->announcements->count() }}, paused: false, urls: @js($this->announcements->map(fn ($announcement) => route('announcements.index', ['duyuru' => $announcement->id]))->values()) }"
    x-init="setInterval(() => { if (! paused && count > 1) index = (index + 1) % count }, 6000)"
>
    @if ($mode === 'banner')
        @foreach ($this->announcements->where('level', 'critical') as $announcement)
            <x-panel.alert variant="danger" :title="$announcement->title" icon="megaphone" wire:key="banner-{{ $announcement->id }}">
                <x-policy-body :document="$announcement" class="!text-inherit [&_p]:mt-0" />
            </x-panel.alert>
        @endforeach
    @elseif ($this->announcements->isNotEmpty())
            <button type="button" x-on:click="Livewire.navigate(urls[index])" x-on:mouseenter="paused = true" x-on:mouseleave="paused = false" data-test="announcement-ticker"
                class="flex h-10 w-full max-w-[560px] min-w-0 items-center gap-2.5 rounded-[10px] border-[1.5px] border-[#E5EEF0] bg-[#F4FAF9] ps-2 pe-1.5 text-start transition hover:border-mint">
                <span class="flex size-[26px] shrink-0 items-center justify-center rounded-[7px] bg-mint text-white">
                    <flux:icon.megaphone variant="micro" class="size-3.5" />
                </span>
                @foreach ($this->announcements as $announcement)
                    <span x-show="index === {{ $loop->index }}" @if (! $loop->first) x-cloak @endif class="flex min-w-0 flex-1 items-center gap-2.5">
                        <x-panel.badge :color="\App\Models\Announcement::CATEGORIES[$announcement->category] ?? 'blue'" :dot="false" class="hidden shrink-0 !text-[11px] xl:inline-flex">
                            {{ $announcement->category }}
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
    @endif
</div>
