<?php

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * Live announcements for the signed-in panel user, at the top of every panel page.
 */
new class extends Component {
    /**
     * @return Collection<int, Announcement>
     */
    #[Computed]
    public function announcements(): Collection
    {
        return Auth::user() ? Announcement::query()->for(Auth::user())->limit(5)->get() : new Collection;
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

<div @class(['space-y-3', 'mb-6' => $this->announcements->isNotEmpty()])>
    @foreach ($this->announcements as $announcement)
        <flux:callout wire:key="announcement-{{ $announcement->id }}"
            :icon="$announcement->level === 'info' ? 'megaphone' : 'exclamation-triangle'"
            :color="['info' => 'sky', 'warning' => 'amber', 'critical' => 'red'][$announcement->level]"
            :heading="$announcement->title">
            <flux:callout.text>
                <x-policy-body :document="$announcement" class="!text-inherit" />
            </flux:callout.text>
            @if ($announcement->isDismissible())
                <x-slot name="controls">
                    <flux:button icon="x-mark" variant="ghost" size="sm" wire:click="dismiss({{ $announcement->id }})" aria-label="Duyuruyu kapat" />
                </x-slot>
            @endif
        </flux:callout>
    @endforeach
</div>
