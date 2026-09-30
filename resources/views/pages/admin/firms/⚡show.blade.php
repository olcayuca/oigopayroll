<?php

use App\Actions\Firms\ReviewFirm;
use App\Enums\FirmStatus;
use App\Enums\ScopeType;
use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Firm;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Firma Detayı')] class extends Component {
    public Firm $firm;

    public string $rejectionReason = '';

    public function mount(Firm $firm): void
    {
        $this->authorize('view', $firm);

        $this->firm = $firm;
    }

    /**
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        return Company::visibleTo(Auth::user())
            ->where('firm_id', $this->firm->id)
            ->with('sector')
            ->withCount('workplaces')
            ->orderBy('company_no')
            ->get();
    }

    /**
     * @return Collection<int, AccessGrant>
     */
    #[Computed]
    public function grants(): Collection
    {
        return AccessGrant::query()
            ->where('scope_type', ScopeType::Firm)
            ->where('scope_id', $this->firm->id)
            ->with(['user', 'template'])
            ->get();
    }

    public function approve(ReviewFirm $reviewFirm): void
    {
        $this->authorize('review', $this->firm);

        $reviewFirm->approve($this->firm, Auth::user());

        Flux::toast(variant: 'success', text: 'Firma onaylandı.');
    }

    public function reject(ReviewFirm $reviewFirm): void
    {
        $this->authorize('review', $this->firm);

        $this->validate(['rejectionReason' => ['required', 'string', 'max:1000']], [], ['rejectionReason' => 'Red gerekçesi']);

        $reviewFirm->reject($this->firm, Auth::user(), $this->rejectionReason);

        $this->reset('rejectionReason');
        Flux::modal('reject-firm')->close();
        Flux::toast(variant: 'success', text: 'Firma reddedildi.');
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('admin.firms.index')" wire:navigate>Firmalar</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $firm->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl">{{ $firm->name }}</flux:heading>
                <flux:badge :color="$firm->status->color()">{{ $firm->status->label() }}</flux:badge>
            </div>
            <flux:text class="mt-1">
                {{ $firm->source->label() }} tarafından {{ $firm->created_at?->format('d.m.Y H:i') }} tarihinde oluşturuldu
                @if ($firm->creator) ({{ $firm->creator->name }}) @endif
            </flux:text>
        </div>

        @if ($firm->status === FirmStatus::Pending)
            @can('review', $firm)
                <div class="flex gap-2">
                    <flux:button variant="primary" color="green" icon="check" wire:click="approve" wire:confirm="Firma onaylansın mı?">Onayla</flux:button>
                    <flux:modal.trigger name="reject-firm">
                        <flux:button variant="danger" icon="x-mark">Reddet</flux:button>
                    </flux:modal.trigger>
                </div>
            @endcan
        @endif
    </div>

    @if ($firm->status === FirmStatus::Pending)
        <flux:callout icon="clock" color="amber" heading="Onay bekliyor"
            text="Firma onaylanana kadar şirket veya işyeri eklenemez ve müşteri paneli kullanılamaz." />
    @elseif ($firm->status === FirmStatus::Rejected)
        <flux:callout icon="x-circle" color="red" heading="Reddedildi"
            text="{{ $firm->rejection_reason }} — {{ $firm->reviewer?->name }}, {{ $firm->reviewed_at?->format('d.m.Y H:i') }}" />
    @elseif ($firm->reviewed_at)
        <flux:text size="sm">Onaylayan: {{ $firm->reviewer?->name }}, {{ $firm->reviewed_at->format('d.m.Y H:i') }}</flux:text>
    @endif

    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Şirketler</flux:heading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>No</flux:table.column>
                <flux:table.column>Unvan</flux:table.column>
                <flux:table.column>Tip</flux:table.column>
                <flux:table.column>Sektör</flux:table.column>
                <flux:table.column>Vergi No</flux:table.column>
                <flux:table.column align="end">İşyeri</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->companies as $company)
                    <flux:table.row :key="$company->id">
                        <flux:table.cell>{{ $company->company_no }}</flux:table.cell>
                        <flux:table.cell variant="strong">
                            {{ $company->title }}
                            <div class="text-xs font-normal text-zinc-500">{{ $company->short_name }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $company->company_type->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $company->sector->name }}</flux:table.cell>
                        <flux:table.cell>{{ $company->tax_number }} <span class="text-zinc-500">/ {{ $company->tax_office }}</span></flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($company->workplaces_count === 0)
                                <flux:badge size="sm" color="amber" inset="top bottom">İşyeri yok</flux:badge>
                            @else
                                {{ $company->workplaces_count }}
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">Bu firmaya ait şirket yok.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </section>

    <section class="space-y-3">
        <flux:heading size="lg">Yetkili Kullanıcılar</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Kullanıcı</flux:table.column>
                <flux:table.column>Tip</flux:table.column>
                <flux:table.column>Yetkiler</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->grants as $grant)
                    <flux:table.row :key="$grant->id">
                        <flux:table.cell variant="strong">
                            {{ $grant->user->name }}
                            <div class="text-xs font-normal text-zinc-500">{{ $grant->user->email }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $grant->user->type->label() }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($grant->template)
                                <flux:badge size="sm" color="indigo" inset="top bottom">{{ $grant->template->name }}</flux:badge>
                            @endif
                            {{ count($grant->effectivePermissions()) }} yetki
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="py-8 text-center text-zinc-500">Bu firmada yetkilendirilmiş kullanıcı yok.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </section>

    <flux:modal name="reject-firm" class="md:w-[28rem]">
        <form wire:submit="reject" class="space-y-6">
            <div>
                <flux:heading size="lg">Firmayı Reddet</flux:heading>
                <flux:text class="mt-1">Gerekçe firmayı oluşturan müşteriye gösterilecektir.</flux:text>
            </div>

            <flux:textarea wire:model="rejectionReason" label="Red gerekçesi" rows="4" required />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Vazgeç</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">Reddet</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
