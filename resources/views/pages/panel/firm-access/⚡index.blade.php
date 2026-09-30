<?php

use App\Actions\Access\DelegatedGrantGuard;
use App\Actions\Firms\CreateSubFirm;
use App\Actions\Firms\ManageFirmLink;
use App\Enums\Permission;
use App\Livewire\PanelComponent;
use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Firm;
use App\Models\FirmLink;
use App\Models\PermissionTemplate;
use App\Models\User;
use App\Models\Workplace;
use App\Validation\FirmRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Title('Firma Erişimleri')] class extends PanelComponent {
    #[Url(as: 'sekme', except: 'yonettiklerimiz')]
    public string $tab = 'yonettiklerimiz';

    // "Manage my firm" (incoming link)
    public ?int $editingLinkId = null;

    public string $taxNumber = '';

    public string $templateId = '';

    /** @var list<string> */
    public array $permissions = [];

    // Assigning our users to a firm we manage
    public ?int $assignLinkId = null;

    public ?int $editingGrantId = null;

    public string $assignUserId = '';

    public string $scopeType = 'firm';

    public string $companyId = '';

    public string $workplaceId = '';

    // Sub-firm
    /** @var array<string, string> */
    public array $firmForm = [];

    public function mount(): void
    {
        $this->authorize('manageUsers', $this->firm);
    }

    /**
     * Only members of the active firm (or HRD) manage its links and assignments.
     */
    #[Computed]
    public function isMember(): bool
    {
        return Auth::user()->isSuperAdmin() || Auth::user()->firm_id === $this->firm->id;
    }

    /**
     * @return Collection<int, FirmLink>
     */
    #[Computed]
    public function managerLinks(): Collection
    {
        return $this->firm->managerLinks()->with('manager')->get();
    }

    /**
     * @return Collection<int, FirmLink>
     */
    #[Computed]
    public function managedLinks(): Collection
    {
        return $this->firm->managedLinks()->with('managed')->get();
    }

    /**
     * Our users' access inside each managed firm, keyed by link id.
     *
     * @return array<int, Collection<int, AccessGrant>>
     */
    #[Computed]
    public function assignments(): array
    {
        $manage = app(ManageFirmLink::class);

        return $this->managedLinks
            ->mapWithKeys(fn (FirmLink $link) => [$link->id => $manage->assignmentsQuery($link)->with(['user', 'template'])->get()])
            ->all();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function members(): Collection
    {
        // Full models: the access rules read type and firm_id.
        return User::where('firm_id', $this->firm->id)->where('is_active', true)->orderBy('name')->get();
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
     * @return list<string>
     */
    #[Computed]
    public function assignable(): array
    {
        $link = $this->assignLinkId ? $this->managedLinks->firstWhere('id', $this->assignLinkId) : null;

        return $link ? app(DelegatedGrantGuard::class)->grantableThroughLink(Auth::user(), $link) : [];
    }

    /**
     * @return Collection<int, Company>
     */
    #[Computed]
    public function managedCompanies(): Collection
    {
        $link = $this->assignLinkId ? $this->managedLinks->firstWhere('id', $this->assignLinkId) : null;

        return $link ? Company::where('firm_id', $link->managed_firm_id)->orderBy('company_no')->get(['id', 'firm_id', 'company_no', 'short_name']) : new Collection;
    }

    /**
     * @return Collection<int, Workplace>
     */
    #[Computed]
    public function managedWorkplaces(): Collection
    {
        return $this->companyId !== '' && $this->managedCompanies->contains('id', (int) $this->companyId)
            ? Workplace::where('company_id', $this->companyId)->orderBy('workplace_no')->get(['id', 'company_id', 'workplace_no', 'branch_name'])
            : new Collection;
    }

    public function updatedCompanyId(): void
    {
        $this->workplaceId = '';
    }

    // ---- Firms that manage us -------------------------------------------------

    public function newLink(): void
    {
        $this->reset('editingLinkId', 'taxNumber', 'templateId', 'permissions');
        $this->resetValidation();

        Flux::modal('firm-link')->show();
    }

    public function editLink(int $linkId): void
    {
        $link = $this->managerLinks->firstWhere('id', $linkId) ?? abort(404);

        $this->editingLinkId = $link->id;
        $this->taxNumber = (string) $link->manager->tax_number;
        $this->templateId = '';
        $this->permissions = $link->permissions;
        $this->resetValidation();

        Flux::modal('firm-link')->show();
    }

    public function saveLink(ManageFirmLink $manageFirmLink): void
    {
        $manager = $this->editingLinkId
            ? ($this->managerLinks->firstWhere('id', $this->editingLinkId)?->manager ?? abort(404))
            : Firm::where('tax_number', trim($this->taxNumber))->first();

        if (! $manager) {
            $this->resetErrorBag();
            $this->addError('taxNumber', 'Bu vergi numarasıyla kayıtlı firma bulunamadı.');

            return;
        }

        $template = $this->templateId !== '' ? PermissionTemplate::find($this->templateId) : null;
        $permissions = [...$this->permissions, ...($template->permissions ?? [])];

        if (! $this->mappingErrors('link', [], fn () => $manageFirmLink->link($manager, $this->firm, $permissions, Auth::user(), delegated: true))) {
            return;
        }

        unset($this->managerLinks);
        $this->tab = 'yonetenler';
        Flux::modal('firm-link')->close();
        Flux::toast(variant: 'success', text: "{$manager->name} artık firmanızı yönetebilir. Kendi kullanıcılarını firmanıza atayacaklar.");
    }

    public function removeLink(int $linkId, ManageFirmLink $manageFirmLink): void
    {
        $link = $this->managerLinks->firstWhere('id', $linkId) ?? abort(404);

        try {
            $manageFirmLink->unlink($link, Auth::user(), delegated: true);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->managerLinks);
        Flux::toast(variant: 'success', text: 'Erişim ve bu firmanın kullanıcı atamaları kaldırıldı.');
    }

    // ---- Our users in firms we manage -------------------------------------------

    public function newAssignment(int $linkId): void
    {
        $this->managedLinks->firstWhere('id', $linkId) ?? abort(404);

        $this->reset('editingGrantId', 'assignUserId', 'companyId', 'workplaceId', 'templateId', 'permissions');
        $this->assignLinkId = $linkId;
        $this->scopeType = 'firm';
        $this->resetValidation();

        Flux::modal('assign-user')->show();
    }

    public function editAssignment(int $linkId, int $grantId): void
    {
        $grant = collect($this->assignments[$linkId] ?? [])->firstWhere('id', $grantId) ?? abort(404);
        $scope = $grant->scopeModel();

        $this->assignLinkId = $linkId;
        $this->editingGrantId = $grant->id;
        $this->assignUserId = (string) $grant->user_id;
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

        Flux::modal('assign-user')->show();
    }

    public function saveAssignment(ManageFirmLink $manageFirmLink): void
    {
        $this->resetErrorBag();
        $link = $this->managedLinks->firstWhere('id', $this->assignLinkId) ?? abort(404);
        $user = $this->members->firstWhere('id', (int) $this->assignUserId);

        if (! $user) {
            $this->addError('assignUserId', 'Firmanızdan bir kullanıcı seçiniz.');

            return;
        }

        $scope = match ($this->scopeType) {
            'company' => $this->managedCompanies->firstWhere('id', (int) $this->companyId),
            'workplace' => $this->managedWorkplaces->firstWhere('id', (int) $this->workplaceId),
            default => $link->managed,
        };

        if (! $scope) {
            $this->addError($this->scopeType === 'company' ? 'companyId' : 'workplaceId', 'Seçim yapınız.');

            return;
        }

        $template = $this->templateId !== '' ? PermissionTemplate::find($this->templateId) : null;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($manageFirmLink, $link, $user, $scope, $template) {
                if ($this->editingGrantId) {
                    $existing = collect($this->assignments[$link->id] ?? [])->firstWhere('id', $this->editingGrantId);
                    $moved = $existing && ($existing->scope_type->value !== $this->scopeType || $existing->scope_id !== $scope->getKey() || $existing->user_id !== $user->id);

                    if ($moved) {
                        $manageFirmLink->unassign($link, $existing, Auth::user());
                    }
                }

                $manageFirmLink->assign($link, $user, $scope, $this->permissions, $template, Auth::user());
            });
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        unset($this->assignments);
        Flux::modal('assign-user')->close();
        Flux::toast(variant: 'success', text: "{$user->name}, {$link->managed->name} firmasına atandı.");
    }

    public function removeAssignment(int $linkId, int $grantId, ManageFirmLink $manageFirmLink): void
    {
        $link = $this->managedLinks->firstWhere('id', $linkId) ?? abort(404);
        $grant = collect($this->assignments[$linkId] ?? [])->firstWhere('id', $grantId) ?? abort(404);

        try {
            $manageFirmLink->unassign($link, $grant, Auth::user());
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->assignments);
        Flux::toast(variant: 'success', text: 'Atama kaldırıldı.');
    }

    // ---- Sub-firm ------------------------------------------------------------------

    public function createSubFirm(CreateSubFirm $createSubFirm): void
    {
        $firm = $this->mappingErrors('firmForm', FirmRules::FIELDS, fn () => $createSubFirm->handle($this->firm, $this->firmForm, Auth::user()));

        if (! $firm) {
            return;
        }

        $this->reset('firmForm');
        unset($this->managedLinks, $this->assignments);
        $this->tab = 'yonettiklerimiz';
        Flux::modal('sub-firm')->close();
        Flux::toast(variant: 'success', text: "\"{$firm->name}\" alt firma olarak oluşturuldu ve HRD onayına gönderildi.");
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Firma Erişimleri</flux:heading>
            <flux:text class="mt-1">
                Kullanıcılar yalnızca kendi firmalarına aittir. Başka bir firmanın işlerini yönetmek için önce iki firma arasında
                yetki kurulur, sonra yönetici firma kendi kullanıcılarını o firmaya tek tek atar.
            </flux:text>
        </div>
        @if ($this->isMember && auth()->user()->hasPermissionOn(Permission::FirmCreateSubfirm, $this->firm))
            <flux:modal.trigger name="sub-firm">
                <flux:button icon="building-office-2">Alt Firma Oluştur</flux:button>
            </flux:modal.trigger>
        @endif
    </div>

    @unless ($this->isMember)
        <flux:callout icon="information-circle" heading="Bu firmanın erişimlerini yalnızca kendi yetkilileri yönetebilir." />
    @endunless

    <x-tabs :active="$tab"
        :tabs="['yonettiklerimiz' => 'Yönettiğimiz Firmalar', 'yonetenler' => 'Firmamızı Yönetenler']"
        :counts="['yonettiklerimiz' => $this->managedLinks->count(), 'yonetenler' => $this->managerLinks->count()]" />

    @if ($tab === 'yonettiklerimiz')
    <section class="space-y-4">
        <div>
            <flux:text size="sm">Firmanızdan kullanıcıları bu firmalara atayın. Verebileceğiniz yetkiler, o firmanın size tanıdığı yetkilerle sınırlıdır.</flux:text>
        </div>

        @forelse ($this->managedLinks as $link)
            <div wire:key="managed-{{ $link->id }}" class="space-y-3 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading>{{ $link->managed->name }}</flux:heading>
                            <flux:badge size="sm" :color="$link->managed->status->color()">{{ $link->managed->status->label() }}</flux:badge>
                            @if ($link->managed->parent_firm_id === $this->firm->id)
                                <flux:badge size="sm" color="violet">Alt firma</flux:badge>
                            @endif
                        </div>
                        <flux:text size="sm" class="mt-1">İzin: {{ collect($link->permissions)->map(fn ($p) => Permission::from($p)->label())->join(', ') }}</flux:text>
                    </div>
                    @if ($this->isMember)
                        <flux:button size="sm" icon="user-plus" wire:click="newAssignment({{ $link->id }})">Kullanıcı Ata</flux:button>
                    @endif
                </div>

                @php($rows = $this->assignments[$link->id] ?? collect())
                @if ($rows->isEmpty())
                    <flux:text size="sm">Bu firmaya henüz kullanıcı atanmadı.</flux:text>
                @else
                    <flux:table>
                        <flux:table.rows>
                            @foreach ($rows as $grant)
                                <flux:table.row :key="'as-'.$grant->id">
                                    <flux:table.cell variant="strong">{{ $grant->user->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $grant->scope_type->label() }}: {{ $grant->scopeLabel() }}</flux:table.cell>
                                    <flux:table.cell class="max-w-md whitespace-normal text-xs text-zinc-500">
                                        {{ collect($grant->effectivePermissions())->map(fn ($p) => Permission::from($p)->label())->join(', ') }}
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="whitespace-nowrap">
                                        @if ($this->isMember)
                                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editAssignment({{ $link->id }}, {{ $grant->id }})" />
                                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeAssignment({{ $link->id }}, {{ $grant->id }})" wire:confirm="Atama kaldırılsın mı?" />
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        @empty
            <flux:text>Yönettiğiniz başka firma yok.</flux:text>
        @endforelse
    </section>

    @endif

    @if ($tab === 'yonetenler')
    <section class="space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <flux:text size="sm">Bu firmalar, kendi kullanıcılarını burada izin verdiğiniz yetkiler kadar firmanıza atayabilir.</flux:text>
            </div>
            @if ($this->isMember)
                <flux:button size="sm" variant="primary" icon="plus" wire:click="newLink">Yönetici Firma Ekle</flux:button>
            @endif
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Firma</flux:table.column>
                <flux:table.column>İzin verilen yetkiler</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->managerLinks as $link)
                    <flux:table.row :key="'mg-'.$link->id">
                        <flux:table.cell variant="strong">
                            {{ $link->manager->name }}
                            <div class="text-xs font-normal text-zinc-500">{{ $link->manager->tax_number }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-md whitespace-normal text-xs text-zinc-500">
                            {{ collect($link->permissions)->map(fn ($p) => Permission::from($p)->label())->join(', ') }}
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap">
                            @if ($this->isMember)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editLink({{ $link->id }})" />
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeLink({{ $link->id }})"
                                    wire:confirm="{{ $link->manager->name }} firmasının erişimi ve kullanıcı atamaları kaldırılsın mı?" />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="py-8 text-center text-zinc-500">Firmanızı yönetebilen başka firma yok.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </section>

    @endif

    <flux:modal name="firm-link" class="md:w-[44rem]">
        <form wire:submit="saveLink" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingLinkId ? 'Erişimi Düzenle' : 'Yönetici Firma Ekle' }}</flux:heading>
                <flux:text class="mt-1">Firmanızı yönetecek firmanın vergi numarasını girin. Yalnızca kendi sahip olduğunuz yetkileri verebilirsiniz.</flux:text>
            </div>
            <flux:input wire:model="taxNumber" label="Yönetici firmanın vergi numarası" inputmode="numeric" maxlength="11" required :disabled="(bool) $editingLinkId" />
            <flux:error name="manager" />
            <x-permission-picker :allowed="$this->grantable" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="assign-user" class="md:w-[44rem]">
        <form wire:submit="saveAssignment" class="space-y-6">
            <flux:heading size="lg">{{ $editingGrantId ? 'Atamayı Düzenle' : 'Firmamızdan Kullanıcı Ata' }}</flux:heading>

            <flux:select wire:model="assignUserId" label="Kullanıcı (firmanızdan)">
                <flux:select.option value="">Seçiniz</flux:select.option>
                @foreach ($this->members as $member)
                    <flux:select.option value="{{ $member->id }}">{{ $member->name }} · {{ $member->email }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="user" />
            <flux:error name="link" />
            <flux:error name="email" />

            <flux:radio.group wire:model.live="scopeType" label="Kapsam" variant="segmented">
                <flux:radio value="firm" label="Tüm firma" />
                <flux:radio value="company" label="Şirket" />
                <flux:radio value="workplace" label="İşyeri" />
            </flux:radio.group>

            @if ($scopeType !== 'firm')
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="companyId" label="Şirket">
                        <flux:select.option value="">Seçiniz</flux:select.option>
                        @foreach ($this->managedCompanies as $company)
                            <flux:select.option value="{{ $company->id }}">{{ $company->company_no }} · {{ $company->short_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @if ($scopeType === 'workplace')
                        <flux:select wire:model="workplaceId" label="İşyeri" :disabled="$companyId === ''">
                            <flux:select.option value="">Seçiniz</flux:select.option>
                            @foreach ($this->managedWorkplaces as $workplace)
                                <flux:select.option value="{{ $workplace->id }}">{{ $workplace->workplace_no }} · {{ $workplace->branch_name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                </div>
            @endif

            <x-permission-picker :allowed="$this->assignable" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="sub-firm" class="md:w-[40rem]">
        <form wire:submit="createSubFirm" class="space-y-6">
            <div>
                <flux:heading size="lg">Alt Firma Oluştur</flux:heading>
                <flux:text class="mt-1">
                    Alt firma <strong>{{ $this->firm->name }}</strong> tarafından yönetilir ve HRD onayından sonra aktif olur.
                    Siz otomatik olarak alt firmaya atanırsınız; diğer kullanıcılarınızı "Kullanıcı Ata" ile ekleyebilirsiniz.
                </flux:text>
            </div>
            @error('firm') <flux:callout icon="exclamation-triangle" color="red" :heading="$message" /> @enderror
            <x-firm-fields />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Oluştur</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
