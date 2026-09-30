<?php

use App\Actions\Firms\AssignSpecialist;
use App\Enums\FirmStatus;
use App\Enums\ScopeType;
use App\Enums\UserType;
use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Uzman Dağılımı')] class extends Component {
    use WithPagination;

    #[Url(as: 'sekme')]
    public string $tab = 'uzmanlar';

    #[Url(as: 'filtre')]
    public string $filter = 'tumu';

    #[Url(as: 'q')]
    public string $search = '';

    public ?int $transferFrom = null;

    public string $transferTo = '';

    public function mount(): void
    {
        $this->authorize('manage-settings');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Payroll specialists with their workload (as responsible specialist).
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function specialists(): Collection
    {
        $specialists = User::query()->where('type', UserType::PayrollSpecialist)->orderByDesc('is_active')->orderBy('name')->get();
        $ids = $specialists->modelKeys();

        $firms = Firm::query()->whereIn('specialist_id', $ids)->where('status', FirmStatus::Active)
            ->selectRaw('specialist_id, count(*) as total')->groupBy('specialist_id')->pluck('total', 'specialist_id');
        $companies = Company::query()->join('firms', 'firms.id', '=', 'companies.firm_id')
            ->whereIn('firms.specialist_id', $ids)->whereNull('firms.deleted_at')
            ->selectRaw('firms.specialist_id, count(*) as total')->groupBy('firms.specialist_id')->pluck('total', 'specialist_id');
        $workplaces = Workplace::query()->join('companies', 'companies.id', '=', 'workplaces.company_id')
            ->join('firms', 'firms.id', '=', 'companies.firm_id')
            ->whereIn('firms.specialist_id', $ids)->whereNull('firms.deleted_at')
            ->selectRaw('firms.specialist_id, count(*) as total')->groupBy('firms.specialist_id')->pluck('total', 'specialist_id');
        $access = AccessGrant::query()->whereIn('user_id', $ids)->where('scope_type', ScopeType::Firm)
            ->selectRaw('user_id, count(*) as total')->groupBy('user_id')->pluck('total', 'user_id');

        return $specialists->each(fn (User $user) => $user->setAttribute('workload', [
            'firms' => (int) ($firms[$user->id] ?? 0),
            'companies' => (int) ($companies[$user->id] ?? 0),
            'workplaces' => (int) ($workplaces[$user->id] ?? 0),
            'access' => (int) ($access[$user->id] ?? 0),
        ]));
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function activeSpecialists(): Collection
    {
        return $this->specialists->where('is_active', true)->values();
    }

    #[Computed]
    public function unassignedCount(): int
    {
        return $this->firmsQuery()->where(fn ($query) => $this->needsSpecialist($query))->count();
    }

    /**
     * @return LengthAwarePaginator<int, Firm>
     */
    #[Computed]
    public function firms(): LengthAwarePaginator
    {
        $firms = $this->firmsQuery()
            ->with('specialist')
            ->withCount(['companies', 'workplaces'])
            ->when($this->filter === 'atanmamis', fn ($query) => $query->where(fn ($inner) => $this->needsSpecialist($inner)))
            ->when($this->filter !== 'tumu' && $this->filter !== 'atanmamis', fn ($query) => $query->where('specialist_id', (int) $this->filter))
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.trim($this->search).'%')->orWhere('title', 'like', '%'.trim($this->search).'%')))
            ->orderBy('name')
            ->paginate(25);

        // Specialists (other than the responsible one) who can also work on each firm.
        $grants = AccessGrant::query()->with('user')
            ->where('scope_type', ScopeType::Firm)
            ->whereIn('scope_id', $firms->pluck('id'))
            ->whereHas('user', fn ($query) => $query->where('type', UserType::PayrollSpecialist))
            ->get()->groupBy('scope_id');

        $firms->getCollection()->each(fn (Firm $firm) => $firm->setAttribute('specialistGrants', $grants->get($firm->id, collect())));

        return $firms;
    }

    public function assign(int $firmId, string $specialistId, AssignSpecialist $action): void
    {
        $this->authorize('manage-settings');

        $firm = Firm::with('specialist')->findOrFail($firmId);
        $specialist = $specialistId === '' ? null : User::findOrFail((int) $specialistId);

        try {
            $action->handle($firm, $specialist, auth()->user());
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->firms, $this->specialists, $this->unassignedCount);
        Flux::toast(variant: 'success', text: $specialist ? "{$firm->name}: sorumlu uzman {$specialist->name}." : "{$firm->name}: sorumlu uzman kaldırıldı.");
    }

    public function openTransfer(int $userId): void
    {
        $this->transferFrom = $userId;
        $this->transferTo = '';
        $this->resetValidation();

        Flux::modal('transfer')->show();
    }

    public function transfer(AssignSpecialist $action): void
    {
        $this->authorize('manage-settings');
        $this->validate(['transferTo' => ['required', 'integer']], ['transferTo.required' => 'Devralacak uzmanı seçin.']);

        $moved = $action->transfer(User::findOrFail($this->transferFrom), User::findOrFail((int) $this->transferTo), auth()->user());

        unset($this->firms, $this->specialists, $this->unassignedCount);
        Flux::modal('transfer')->close();
        Flux::toast(variant: 'success', text: "{$moved} firma devredildi.");
    }

    /**
     * Firms that need a responsible specialist: approved or awaiting approval.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Firm>
     */
    private function firmsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Firm::query()->whereIn('status', [FirmStatus::Active, FirmStatus::Pending]);
    }

    /**
     * No specialist, or the specialist can no longer work (deactivated / no longer a specialist).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Firm>  $query
     */
    private function needsSpecialist(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNull('specialist_id')->orWhereHas('specialist', fn ($users) => $users
            ->where('is_active', false)->orWhere('type', '!=', UserType::PayrollSpecialist));
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">Uzman Dağılımı</flux:heading>
        <flux:text class="mt-1">
            Her firmanın sorumlu bordro uzmanı. Sorumlu atanan uzmana firma üzerinde "Bordro Uzmanı" yetkisi otomatik verilir;
            uzman değişince eski uzmanın firma yetkisi kaldırılır (yedek uzmanlar Kullanıcılar sayfasından yetkilendirilir).
        </flux:text>
    </div>

    <x-tabs :active="$tab" :tabs="['uzmanlar' => 'Uzmanlar', 'firmalar' => 'Firmalar']" :counts="['firmalar' => $this->unassignedCount]" />

    @if ($tab === 'uzmanlar')
        @if ($this->unassignedCount > 0)
            <flux:callout icon="exclamation-triangle" color="amber" heading="{{ $this->unassignedCount }} firmanın sorumlu uzmanı yok veya uzmanı pasif">
                <x-slot name="actions">
                    <flux:button size="sm" wire:click="$set('tab', 'firmalar'); $set('filter', 'atanmamis')">Firmaları göster</flux:button>
                </x-slot>
            </flux:callout>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Uzman</flux:table.column>
                <flux:table.column align="end">Sorumlu firma</flux:table.column>
                <flux:table.column align="end">Şirket</flux:table.column>
                <flux:table.column align="end">İşyeri</flux:table.column>
                <flux:table.column align="end">Erişebildiği firma</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->specialists as $specialist)
                    @php($load = $specialist->getAttribute('workload'))
                    <flux:table.row :key="$specialist->id">
                        <flux:table.cell>
                            <a href="{{ route('admin.users.show', $specialist) }}" wire:navigate class="font-medium hover:underline">{{ $specialist->name }}</a>
                            @unless ($specialist->is_active) <flux:badge size="sm" color="zinc" inset="top bottom">Pasif</flux:badge> @endunless
                            <div class="text-xs text-zinc-500">{{ $specialist->email }}</div>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <button type="button" class="hover:underline" wire:click="$set('tab', 'firmalar'); $set('filter', '{{ $specialist->id }}')">{{ $load['firms'] }}</button>
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ $load['companies'] }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $load['workplaces'] }}</flux:table.cell>
                        <flux:table.cell align="end" class="text-zinc-500">{{ $load['access'] }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($load['firms'] > 0)
                                <flux:button size="sm" variant="ghost" icon="arrows-right-left" wire:click="openTransfer({{ $specialist->id }})">Devret</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500">
                            Bordro uzmanı yok. Kullanıcılar sayfasından "HRD Bordro Uzmanı" tipinde kullanıcı oluşturun.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    @if ($tab === 'firmalar')
        <div class="flex flex-wrap gap-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Firma ara" class="max-w-xs" />
            <flux:select wire:model.live="filter" class="max-w-xs">
                <flux:select.option value="tumu">Tüm firmalar</flux:select.option>
                <flux:select.option value="atanmamis">Sorumlusu olmayan / pasif</flux:select.option>
                @foreach ($this->specialists as $specialist)
                    <flux:select.option value="{{ $specialist->id }}">{{ $specialist->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table :paginate="$this->firms">
            <flux:table.columns>
                <flux:table.column>Firma</flux:table.column>
                <flux:table.column align="end">Şirket / İşyeri</flux:table.column>
                <flux:table.column>Sorumlu uzman</flux:table.column>
                <flux:table.column>Diğer uzmanlar</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->firms as $firm)
                    @php($others = $firm->getAttribute('specialistGrants')->reject(fn ($grant) => $grant->user_id === $firm->specialist_id))
                    <flux:table.row :key="$firm->id">
                        <flux:table.cell>
                            <a href="{{ route('admin.firms.show', $firm) }}" wire:navigate class="font-medium hover:underline">{{ $firm->name }}</a>
                            @if ($firm->status === FirmStatus::Pending) <flux:badge size="sm" color="amber" inset="top bottom">Onay bekliyor</flux:badge> @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ $firm->companies_count }} / {{ $firm->workplaces_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:select size="sm" class="min-w-48" wire:key="assign-{{ $firm->id }}-{{ $firm->specialist_id }}"
                                wire:change="assign({{ $firm->id }}, $event.target.value)">
                                <flux:select.option value="" :selected="$firm->specialist_id === null">— Atanmamış —</flux:select.option>
                                @if ($firm->specialist && ! $this->activeSpecialists->contains($firm->specialist))
                                    <flux:select.option value="{{ $firm->specialist_id }}" selected disabled>{{ $firm->specialist->name }} (pasif)</flux:select.option>
                                @endif
                                @foreach ($this->activeSpecialists as $specialist)
                                    <flux:select.option value="{{ $specialist->id }}" :selected="$firm->specialist_id === $specialist->id">{{ $specialist->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            @if ($firm->specialist && ! $firm->getAttribute('specialistGrants')->contains('user_id', $firm->specialist_id))
                                <flux:text size="sm" class="mt-1 text-amber-600">Uzmanın bu firmada yetkisi kaldırılmış.</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @foreach ($others as $grant)
                                <flux:badge size="sm" inset="top bottom">{{ $grant->user->name }}</flux:badge>
                            @endforeach
                            @if ($others->isEmpty()) <span class="text-zinc-400">—</span> @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-10 text-center text-zinc-500">Firma bulunamadı.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="transfer" class="md:w-[32rem]">
        <form wire:submit="transfer" class="space-y-5">
            <flux:heading size="lg">Firmaları devret</flux:heading>
            <flux:text>
                {{ $this->specialists->firstWhere('id', $transferFrom)?->name }} uzmanının sorumlu olduğu
                {{ $this->specialists->firstWhere('id', $transferFrom)?->getAttribute('workload')['firms'] ?? 0 }} aktif firması (onay bekleyenler dahil tümü)
                seçilen uzmana devredilir; firma yetkileri de devrolur.
            </flux:text>
            <flux:select wire:model="transferTo" label="Devralacak uzman" placeholder="Seçin…">
                @foreach ($this->activeSpecialists->where('id', '!=', $transferFrom) as $specialist)
                    <flux:select.option value="{{ $specialist->id }}">{{ $specialist->name }} ({{ $specialist->getAttribute('workload')['firms'] }} firma)</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Devret</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
