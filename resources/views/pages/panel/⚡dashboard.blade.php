<?php

use App\Actions\Firms\RegisterFirm;
use App\Enums\FirmStatus;
use App\Models\Company;
use App\Models\Firm;
use App\Models\Workplace;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Gösterge Paneli')] class extends Component {
    public string $firmName = '';

    #[Computed]
    public function firm(): ?Firm
    {
        return Auth::user()->activeFirm();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $user = Auth::user();
        $firmId = $this->firm?->id;

        return [
            'companies' => Company::visibleTo($user)->where('firm_id', $firmId)->count(),
            'workplaces' => Workplace::visibleTo($user)->whereHas('company', fn ($query) => $query->where('firm_id', $firmId))->count(),
            'companiesWithoutWorkplace' => Company::visibleTo($user)->where('firm_id', $firmId)->withoutWorkplaces()->count(),
        ];
    }

    /**
     * A client without any firm creates one here; it then waits for HRD approval.
     */
    public function registerFirm(RegisterFirm $registerFirm): void
    {
        $this->authorize('register', Firm::class);

        $firm = $registerFirm->handle(Auth::user(), ['name' => $this->firmName]);
        Auth::user()->switchFirm($firm);

        $this->reset('firmName');
        unset($this->firm);

        Flux::toast(variant: 'success', text: 'Firmanız oluşturuldu ve HRD onayına gönderildi.');
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">Hoş geldiniz, {{ auth()->user()->name }}</flux:heading>
        <flux:text class="mt-1">
            {{ auth()->user()->type->label() }}
            @if ($this->firm) · {{ $this->firm->name }} @endif
        </flux:text>
    </div>

    @if (! $this->firm)
        @can('register', \App\Models\Firm::class)
            <div class="max-w-xl rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading size="lg">Firmanızı oluşturun</flux:heading>
                <flux:text class="mt-1">
                    Firmanız HRD tarafından onaylandıktan sonra şirket ve işyeri bilgilerinizi girmeye başlayabilirsiniz.
                </flux:text>

                <form wire:submit="registerFirm" class="mt-6 flex items-end gap-3">
                    <div class="flex-1">
                        <flux:input wire:model="firmName" label="Firma Adı" required />
                    </div>
                    <flux:button type="submit" variant="primary">Oluştur</flux:button>
                </form>
                @error('name') <flux:text class="mt-2 text-red-500">{{ $message }}</flux:text> @enderror
            </div>
        @else
            <flux:callout icon="information-circle" heading="Yetkili olduğunuz firma yok"
                text="Çalışabilmeniz için bir süper adminin size firma, şirket veya işyeri yetkisi vermesi gerekiyor." />
        @endcan
    @elseif ($this->firm->status === FirmStatus::Pending)
        <flux:callout icon="clock" color="amber" heading="Firmanız onay bekliyor"
            text="HRD ekibi firmanızı inceliyor. Onaylandığında şirket ve işyeri işlemlerini yapabileceksiniz." />
    @elseif ($this->firm->status === FirmStatus::Rejected)
        <flux:callout icon="x-circle" color="red" heading="Firma başvurunuz reddedildi"
            text="Gerekçe: {{ $this->firm->rejection_reason }}" />
    @elseif ($this->firm->status === FirmStatus::Passive)
        <flux:callout icon="pause-circle" heading="Firma pasif durumda"
            text="Bu firma üzerinde işlem yapılamaz. Ayrıntılar için HRD ile iletişime geçin." />
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text>Şirket</flux:text>
                <div class="mt-2 text-3xl font-semibold">{{ $this->stats['companies'] }}</div>
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text>İşyeri</flux:text>
                <div class="mt-2 text-3xl font-semibold">{{ $this->stats['workplaces'] }}</div>
            </div>
        </div>

        @if ($this->stats['companies'] === 0)
            <flux:callout icon="building-office" heading="Henüz şirket yok"
                text="İlk adım olarak firmanıza bağlı şirket(ler)i oluşturun; ardından her şirket için en az bir işyeri tanımlayın." />
        @elseif ($this->stats['companiesWithoutWorkplace'] > 0)
            <flux:callout icon="exclamation-triangle" color="amber"
                heading="{{ $this->stats['companiesWithoutWorkplace'] }} şirketin henüz işyeri yok"
                text="Her şirketin altında en az bir işyeri tanımlanması zorunludur." />
        @endif
    @endif
</div>
