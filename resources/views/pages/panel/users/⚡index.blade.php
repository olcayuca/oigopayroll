<?php

use App\Actions\Access\DelegatedGrantGuard;
use App\Actions\Access\GrantAccess;
use App\Actions\Users\AddFirmUser;
use App\Enums\ScopeType;
use App\Livewire\PanelComponent;
use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Firm;
use App\Models\PermissionTemplate;
use App\Models\Workplace;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('Firma Kullanıcıları')] class extends PanelComponent {
    public ?int $editingGrantId = null;

    public string $email = '';

    public string $name = '';

    public string $scopeType = 'firm';

    public string $companyId = '';

    public string $workplaceId = '';

    public string $templateId = '';

    /** @var list<string> */
    public array $permissions = [];

    public ?string $createdEmail = null;

    public ?string $createdPassword = null;

    public function mount(): void
    {
        $this->authorize('manageUsers', $this->firm);
    }

    /**
     * Every grant inside the active firm, grouped by user.
     *
     * @return \Illuminate\Support\Collection<int, Collection<int, AccessGrant>>
     */
    #[Computed]
    public function grantsByUser(): \Illuminate\Support\Collection
    {
        $companyIds = Company::where('firm_id', $this->firm->id)->pluck('id');
        $workplaceIds = Workplace::whereIn('company_id', $companyIds)->pluck('id');

        return AccessGrant::query()
            ->where(fn (Builder $query) => $query
                ->where(fn ($q) => $q->where('scope_type', ScopeType::Firm)->where('scope_id', $this->firm->id))
                ->orWhere(fn ($q) => $q->where('scope_type', ScopeType::Company)->whereIn('scope_id', $companyIds))
                ->orWhere(fn ($q) => $q->where('scope_type', ScopeType::Workplace)->whereIn('scope_id', $workplaceIds)))
            ->with(['user', 'template'])
            ->get()
            ->sortBy(fn (AccessGrant $grant) => $grant->user->name)
            ->groupBy('user_id');
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function grantable(): array
    {
        return app(DelegatedGrantGuard::class)->grantable(Auth::user(), $this->firm);
    }

    /**
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        return Company::where('firm_id', $this->firm->id)->orderBy('company_no')->get(['id', 'firm_id', 'company_no', 'short_name']);
    }

    /**
     * @return Collection<int, Workplace>
     */
    #[Computed]
    public function workplaces(): Collection
    {
        return $this->companyId === ''
            ? new Collection
            : Workplace::where('company_id', $this->companyId)->orderBy('workplace_no')->get(['id', 'company_id', 'workplace_no', 'branch_name']);
    }

    public function updatedCompanyId(): void
    {
        $this->workplaceId = '';
    }

    public function newUser(): void
    {
        $this->reset('editingGrantId', 'email', 'name', 'companyId', 'workplaceId', 'templateId', 'permissions');
        $this->scopeType = 'firm';
        $this->resetValidation();

        Flux::modal('firm-user')->show();
    }

    public function editGrant(int $grantId): void
    {
        $grant = $this->findGrant($grantId);
        $scope = $grant->scopeModel();

        $this->editingGrantId = $grant->id;
        $this->email = $grant->user->email;
        $this->name = $grant->user->name;
        $this->scopeType = $grant->scope_type->value;
        $this->companyId = match (true) {
            $scope instanceof Company => (string) $scope->id,
            $scope instanceof Workplace => (string) $scope->company_id,
            default => '',
        };
        $this->workplaceId = $scope instanceof Workplace ? (string) $scope->id : '';
        $this->templateId = (string) ($grant->permission_template_id ?? '');
        $this->permissions = $grant->permissions ?? [];
        $this->resetValidation();

        Flux::modal('firm-user')->show();
    }

    public function save(AddFirmUser $addFirmUser): void
    {
        $this->authorize('manageUsers', $this->firm);
        $this->resetErrorBag();

        $this->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ], [], ['email' => 'E-posta', 'name' => 'Ad Soyad']);

        $scope = $this->resolveScope();

        if ($scope === null) {
            return;
        }

        try {
            $existing = $this->editingGrantId ? $this->findGrant($this->editingGrantId) : null;

            \Illuminate\Support\Facades\DB::transaction(function () use ($addFirmUser, $scope, $existing) {
                // Moving a grant to another scope replaces it.
                if ($existing && ($existing->scope_type->value !== $this->scopeType || $existing->scope_id !== $scope->getKey())) {
                    $existing->delete();
                    $existing->user->flushAccessCache();
                }

                $result = $addFirmUser->handle(
                    $scope,
                    $this->email,
                    $this->name,
                    $this->permissions,
                    $this->templateId !== '' ? PermissionTemplate::find($this->templateId) : null,
                    Auth::user(),
                    delegated: true,
                );

                $this->createdEmail = $result['password'] ? $result['user']->email : null;
                $this->createdPassword = $result['password'];
            });
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        unset($this->grantsByUser);
        Flux::modal('firm-user')->close();

        if ($this->createdPassword) {
            Flux::modal('temporary-password')->show();
        } else {
            Flux::toast(variant: 'success', text: 'Yetki kaydedildi.');
        }
    }

    public function removeGrant(int $grantId, GrantAccess $grantAccess): void
    {
        $this->authorize('manageUsers', $this->firm);

        $grant = $this->findGrant($grantId);

        if ($grant->user->is(Auth::user()) || (! Auth::user()->isSuperAdmin() && ! $grant->user->type->isClient())) {
            Flux::toast(variant: 'danger', text: 'Bu yetkiyi buradan kaldıramazsınız.');

            return;
        }

        $scope = $grant->scopeModel();
        if ($scope) {
            $grantAccess->revoke($grant->user, $scope);
        }

        unset($this->grantsByUser);
        Flux::toast(variant: 'success', text: 'Yetki kaldırıldı.');
    }

    private function resolveScope(): Firm|Company|Workplace|null
    {
        $scope = match ($this->scopeType) {
            'company' => $this->companies->firstWhere('id', (int) $this->companyId),
            'workplace' => $this->workplaceId !== ''
                ? Workplace::whereIn('company_id', $this->companies->pluck('id'))->find($this->workplaceId)
                : null,
            default => $this->firm,
        };

        if ($scope === null) {
            $this->addError($this->scopeType === 'company' ? 'companyId' : 'workplaceId', 'Seçim yapınız.');
        }

        return $scope;
    }

    private function findGrant(int $grantId): AccessGrant
    {
        return $this->grantsByUser->flatten()->firstWhere('id', $grantId) ?? abort(404);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Firma Kullanıcıları</flux:heading>
            <flux:text class="mt-1">{{ $this->firm->name }} firmasında kimin neye erişebileceğini yönetin. Yalnızca kendi sahip olduğunuz yetkileri verebilirsiniz.</flux:text>
        </div>
        <flux:button variant="primary" icon="user-plus" wire:click="newUser">Kullanıcı Ekle</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Kullanıcı</flux:table.column>
            <flux:table.column>Kapsam</flux:table.column>
            <flux:table.column>Yetkiler</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->grantsByUser as $grants)
                @foreach ($grants as $grant)
                    @php($editable = ! $grant->user->is(auth()->user()) && (auth()->user()->isSuperAdmin() || $grant->user->type->isClient()))
                    <flux:table.row :key="$grant->id">
                        <flux:table.cell variant="strong">
                            @if ($loop->first)
                                {{ $grant->user->name }}
                                @unless ($grant->user->type->isClient()) <flux:badge size="sm" color="indigo" inset="top bottom">HRD</flux:badge> @endunless
                                @unless ($grant->user->is_active) <flux:badge size="sm" color="zinc" inset="top bottom">Pasif</flux:badge> @endunless
                                <div class="text-xs font-normal text-zinc-500">{{ $grant->user->email }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" inset="top bottom">{{ $grant->scope_type->label() }}</flux:badge>
                            {{ $grant->scopeLabel() }}
                        </flux:table.cell>
                        <flux:table.cell class="max-w-md whitespace-normal text-xs text-zinc-500">
                            @if ($grant->template) <flux:badge size="sm" color="indigo">{{ $grant->template->name }}</flux:badge> @endif
                            {{ collect($grant->effectivePermissions())->map(fn ($p) => \App\Enums\Permission::from($p)->label())->join(', ') }}
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap">
                            @if ($editable)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editGrant({{ $grant->id }})" />
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeGrant({{ $grant->id }})" wire:confirm="Bu yetki kaldırılsın mı?" />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">Kullanıcı yok.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="firm-user" class="md:w-[44rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingGrantId ? 'Yetkiyi Düzenle' : 'Kullanıcı Ekle' }}</flux:heading>
                @unless ($editingGrantId)
                    <flux:text class="mt-1">E-posta kayıtlıysa kullanıcıya yetki verilir; değilse yeni kullanıcı oluşturulur ve geçici şifresi gösterilir.</flux:text>
                @endunless
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="email" type="email" label="E-posta" required :disabled="(bool) $editingGrantId" />
                @unless ($editingGrantId)
                    <flux:input wire:model="name" label="Ad Soyad (yeni kullanıcı için)" />
                @endunless
            </div>
            <flux:error name="scope" />

            <flux:radio.group wire:model.live="scopeType" label="Kapsam" variant="segmented">
                <flux:radio value="firm" label="Tüm firma" />
                <flux:radio value="company" label="Şirket" />
                <flux:radio value="workplace" label="İşyeri" />
            </flux:radio.group>

            @if ($scopeType !== 'firm')
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="companyId" label="Şirket">
                        <flux:select.option value="">Seçiniz</flux:select.option>
                        @foreach ($this->companies as $company)
                            <flux:select.option value="{{ $company->id }}">{{ $company->company_no }} · {{ $company->short_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @if ($scopeType === 'workplace')
                        <flux:select wire:model="workplaceId" label="İşyeri" :disabled="$companyId === ''">
                            <flux:select.option value="">Seçiniz</flux:select.option>
                            @foreach ($this->workplaces as $workplace)
                                <flux:select.option value="{{ $workplace->id }}">{{ $workplace->workplace_no }} · {{ $workplace->branch_name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                </div>
            @endif

            <x-permission-picker :allowed="$this->grantable" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    </flux:modal>

    <x-temporary-password-modal :email="$createdEmail" :password="$createdPassword" />
</div>
