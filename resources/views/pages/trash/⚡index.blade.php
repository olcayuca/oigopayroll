<?php

use App\Actions\Trash\ManageTrash;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Workplace;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Çöp kutusu, on both portals:
 * - panel: the active firm's deleted records the user can see; restore needs the delete permission
 * - admin: every firm; restore and permanent deletion
 */
new #[Title('Çöp Kutusu')] class extends Component {
    use WithPagination;

    #[Url(as: 'sekme')]
    public string $tab = 'sirketler';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Locked]
    public bool $admin = false;

    public function mount(): void
    {
        $this->admin = Portal::fromHost(request()->getHost()) === Portal::Admin;

        if ($this->admin) {
            $this->authorize('manage-settings');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function firmId(): ?int
    {
        if ($this->admin) {
            return null;
        }

        $firm = Auth::user()->activeFirm();
        abort_if($firm === null, 403, 'Yetkili olduğunuz bir firma bulunmuyor.');

        return $firm->id;
    }

    /**
     * @return LengthAwarePaginator<int, Company>
     */
    #[Computed]
    public function companies(): LengthAwarePaginator
    {
        return Company::onlyTrashed()
            ->with('firm')
            ->unless($this->admin, fn ($query) => $query->visibleTo(Auth::user())->where('firm_id', $this->firmId))
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', '%'.trim($this->search).'%')
                ->orWhere('short_name', 'like', '%'.trim($this->search).'%')
                ->orWhere('company_no', 'like', '%'.trim($this->search).'%')))
            ->latest('deleted_at')
            ->paginate(25, pageName: 'sirket');
    }

    /**
     * @return LengthAwarePaginator<int, Workplace>
     */
    #[Computed]
    public function workplaces(): LengthAwarePaginator
    {
        return Workplace::onlyTrashed()
            ->with(['company' => fn ($query) => $query->withTrashed()->with('firm')])
            ->unless($this->admin, fn ($query) => $query->visibleTo(Auth::user())
                ->whereIn('company_id', Company::query()->select('id')->where('firm_id', $this->firmId)))
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('branch_name', 'like', '%'.trim($this->search).'%')
                ->orWhere('title', 'like', '%'.trim($this->search).'%')
                ->orWhere('sgk_registry_no', 'like', '%'.trim($this->search).'%')))
            ->latest('deleted_at')
            ->paginate(25, pageName: 'isyeri');
    }

    /**
     * @return LengthAwarePaginator<int, Employee>
     */
    #[Computed]
    public function employees(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Employee::onlyTrashed()
            ->with(['firm', 'workplace' => fn ($query) => $query->withTrashed()->with(['company' => fn ($query) => $query->withTrashed()])])
            ->unless($this->admin, fn ($query) => $query->where('firm_id', $this->firmId)->whereIn('workplace_id', $this->viewableWorkplaceIds()))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('registry_no', 'like', $search.'%')
                ->orWhere('first_name', 'like', '%'.$search.'%')
                ->orWhere('last_name', 'like', '%'.$search.'%')))
            ->latest('deleted_at')
            ->paginate(25, pageName: 'personel');
    }

    /**
     * @return Collection<int, \App\Models\AuditLog>
     */
    #[Computed]
    public function deletions(): Collection
    {
        $trash = app(ManageTrash::class);

        return match ($this->tab) {
            'isyerleri' => $trash->deletions($this->workplaces->getCollection()),
            'personel' => $trash->deletions($this->employees->getCollection()),
            default => $trash->deletions($this->companies->getCollection()),
        };
    }

    public function restore(string $type, int $id, ManageTrash $trash): void
    {
        $record = $this->find($type, $id);
        $this->authorize('restore', $record);

        $this->attempt(fn () => $trash->restore($record), 'Kayıt geri alındı.');
    }

    public function purge(string $type, int $id, ManageTrash $trash): void
    {
        abort_unless($this->admin, 403);
        $this->authorize('manage-settings');

        $this->attempt(fn () => $trash->purge($this->find($type, $id)), 'Kayıt kalıcı olarak silindi.');
    }

    private function find(string $type, int $id): Company|Workplace|Employee
    {
        $record = match ($type) {
            'company' => Company::onlyTrashed()->findOrFail($id),
            'employee' => Employee::onlyTrashed()->findOrFail($id),
            default => Workplace::onlyTrashed()->findOrFail($id),
        };

        // Panel users only reach their active firm's records.
        if (! $this->admin) {
            $firmId = $record instanceof Workplace ? Company::withTrashed()->find($record->company_id)?->firm_id : $record->firm_id;
            abort_unless($firmId === $this->firmId, 404);
        }

        return $record;
    }

    /**
     * Workplaces of the active firm (deleted ones too) where the user may view personnel.
     *
     * @return list<int>
     */
    private function viewableWorkplaceIds(): array
    {
        return Workplace::withTrashed()->visibleTo(Auth::user())
            ->whereIn('company_id', Company::withTrashed()->select('id')->where('firm_id', $this->firmId))
            ->with(['company' => fn ($query) => $query->withTrashed()])
            ->get()
            ->filter(fn (Workplace $workplace) => Auth::user()->hasPermissionOn(Permission::EmployeeView, $workplace))
            ->map(fn (Workplace $workplace): int => $workplace->id)
            ->values()
            ->all();
    }

    private function attempt(Closure $action, string $message): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->companies, $this->workplaces, $this->employees, $this->deletions);
        Flux::toast(variant: 'success', text: $message);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">Çöp Kutusu</flux:heading>
        <flux:text class="mt-1">
            Silinen şirket, işyeri ve personel kayıtları burada tutulur ve geri alınabilir.
            @if ($admin)
                Kalıcı silme geri alınamaz; işlem kayıtları saklanır.
            @else
                Şirketi silinmiş işyerini ya da işyeri silinmiş personeli geri almak için önce üst kaydı geri alın.
            @endif
        </flux:text>
    </div>

    <x-tabs :active="$tab" :tabs="['sirketler' => 'Şirketler', 'isyerleri' => 'İşyerleri', 'personel' => 'Personel']"
        :counts="['sirketler' => $this->companies->total(), 'isyerleri' => $this->workplaces->total(), 'personel' => $this->employees->total()]" />

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Ara" class="max-w-sm" />

    @if ($tab === 'sirketler')
        <flux:table :paginate="$this->companies">
            <flux:table.columns>
                <flux:table.column>Şirket</flux:table.column>
                @if ($admin) <flux:table.column>Firma</flux:table.column> @endif
                <flux:table.column>Silinme</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->companies as $company)
                    @php($deletion = $this->deletions->get($company->id))
                    <flux:table.row :key="'c'.$company->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $company->title }}</div>
                            <div class="text-xs text-zinc-500">{{ $company->company_no }} · {{ $company->short_name }}</div>
                        </flux:table.cell>
                        @if ($admin) <flux:table.cell>{{ $company->firm->name ?? '—' }}</flux:table.cell> @endif
                        <flux:table.cell class="whitespace-nowrap">
                            {{ $company->deleted_at?->format('d.m.Y H:i') }}
                            @if ($deletion?->user) <div class="text-xs text-zinc-500">{{ $deletion->user->name }}</div> @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap">
                            @can('restore', $company)
                                <flux:button size="sm" icon="arrow-uturn-left" wire:click="restore('company', {{ $company->id }})">Geri al</flux:button>
                            @endcan
                            @if ($admin)
                                <flux:button size="sm" variant="danger" icon="trash" wire:click="purge('company', {{ $company->id }})"
                                    wire:confirm="{{ $company->title }} kalıcı olarak silinecek. Bu işlem geri alınamaz. Devam edilsin mi?">Kalıcı sil</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-10 text-center text-zinc-500">Silinmiş şirket yok.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    @if ($tab === 'isyerleri')
        <flux:table :paginate="$this->workplaces">
            <flux:table.columns>
                <flux:table.column>İşyeri</flux:table.column>
                <flux:table.column>Şirket</flux:table.column>
                @if ($admin) <flux:table.column>Firma</flux:table.column> @endif
                <flux:table.column>Silinme</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->workplaces as $workplace)
                    @php($deletion = $this->deletions->get($workplace->id))
                    <flux:table.row :key="'w'.$workplace->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $workplace->branch_name }}</div>
                            <div class="text-xs text-zinc-500">{{ $workplace->sgk_registry_no }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $workplace->company->short_name ?? $workplace->company->title ?? '—' }}
                            @if ($workplace->company?->trashed()) <flux:badge size="sm" color="amber" inset="top bottom">Silinmiş</flux:badge> @endif
                        </flux:table.cell>
                        @if ($admin) <flux:table.cell>{{ $workplace->company->firm->name ?? '—' }}</flux:table.cell> @endif
                        <flux:table.cell class="whitespace-nowrap">
                            {{ $workplace->deleted_at?->format('d.m.Y H:i') }}
                            @if ($deletion?->user) <div class="text-xs text-zinc-500">{{ $deletion->user->name }}</div> @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap">
                            @if ($admin || ! $workplace->company?->trashed())
                                @can('restore', $workplace)
                                    <flux:button size="sm" icon="arrow-uturn-left" wire:click="restore('workplace', {{ $workplace->id }})">Geri al</flux:button>
                                @endcan
                            @endif
                            @if ($admin)
                                <flux:button size="sm" variant="danger" icon="trash" wire:click="purge('workplace', {{ $workplace->id }})"
                                    wire:confirm="{{ $workplace->branch_name }} kalıcı olarak silinecek. Bu işlem geri alınamaz. Devam edilsin mi?">Kalıcı sil</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-10 text-center text-zinc-500">Silinmiş işyeri yok.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    @if ($tab === 'personel')
        <flux:table :paginate="$this->employees">
            <flux:table.columns>
                <flux:table.column>Personel</flux:table.column>
                <flux:table.column>İşyeri</flux:table.column>
                @if ($admin) <flux:table.column>Firma</flux:table.column> @endif
                <flux:table.column>Silinme</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->employees as $employee)
                    @php($deletion = $this->deletions->get($employee->id))
                    <flux:table.row :key="'e'.$employee->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $employee->fullName() }}</div>
                            <div class="text-xs text-zinc-500">Sicil {{ $employee->registry_no }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $employee->workplace?->company?->short_name }} / {{ $employee->workplace?->branch_name ?? '—' }}
                            @if ($employee->workplace?->trashed() || $employee->workplace?->company?->trashed())
                                <flux:badge size="sm" color="amber" inset="top bottom">Silinmiş</flux:badge>
                            @endif
                        </flux:table.cell>
                        @if ($admin) <flux:table.cell>{{ $employee->firm->name ?? '—' }}</flux:table.cell> @endif
                        <flux:table.cell class="whitespace-nowrap">
                            {{ $employee->deleted_at?->format('d.m.Y H:i') }}
                            @if ($deletion?->user) <div class="text-xs text-zinc-500">{{ $deletion->user->name }}</div> @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap">
                            @can('restore', $employee)
                                <flux:button size="sm" icon="arrow-uturn-left" wire:click="restore('employee', {{ $employee->id }})">Geri al</flux:button>
                            @endcan
                            @if ($admin)
                                <flux:button size="sm" variant="danger" icon="trash" wire:click="purge('employee', {{ $employee->id }})"
                                    wire:confirm="Sicil {{ $employee->registry_no }} kalıcı olarak silinecek. Bu işlem geri alınamaz. Devam edilsin mi?">Kalıcı sil</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-10 text-center text-zinc-500">Silinmiş personel yok.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif
</div>
