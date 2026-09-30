<?php

use App\Enums\FirmStatus;
use App\Models\Company;
use App\Models\Firm;
use App\Models\Workplace;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Gösterge Paneli')] class extends Component {
    /**
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $user = Auth::user();

        return [
            'pending' => Firm::visibleTo($user)->where('status', FirmStatus::Pending)->count(),
            'active' => Firm::visibleTo($user)->where('status', FirmStatus::Active)->count(),
            'companies' => Company::visibleTo($user)->count(),
            'workplaces' => Workplace::visibleTo($user)->count(),
            'companiesWithoutWorkplace' => Company::visibleTo($user)->withoutWorkplaces()->count(),
        ];
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">Hoş geldiniz, {{ auth()->user()->name }}</flux:heading>
        <flux:text class="mt-1">{{ auth()->user()->type->label() }}</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('admin.firms.index', ['status' => 'pending']) }}" wire:navigate
           class="rounded-xl border border-zinc-200 p-5 transition hover:border-amber-400 dark:border-zinc-700">
            <flux:text>Onay bekleyen firma</flux:text>
            <div class="mt-2 text-3xl font-semibold {{ $this->stats['pending'] ? 'text-amber-500' : '' }}">{{ $this->stats['pending'] }}</div>
        </a>

        <a href="{{ route('admin.firms.index', ['status' => 'active']) }}" wire:navigate
           class="rounded-xl border border-zinc-200 p-5 transition hover:border-zinc-400 dark:border-zinc-700">
            <flux:text>Aktif firma</flux:text>
            <div class="mt-2 text-3xl font-semibold">{{ $this->stats['active'] }}</div>
        </a>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>Şirket</flux:text>
            <div class="mt-2 text-3xl font-semibold">{{ $this->stats['companies'] }}</div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>İşyeri</flux:text>
            <div class="mt-2 text-3xl font-semibold">{{ $this->stats['workplaces'] }}</div>
        </div>
    </div>

    @if ($this->stats['companiesWithoutWorkplace'] > 0)
        <flux:callout icon="exclamation-triangle" color="amber"
            heading="{{ $this->stats['companiesWithoutWorkplace'] }} şirketin henüz işyeri yok"
            text="Her şirketin altında en az bir işyeri tanımlanması zorunludur." />
    @endif
</div>
