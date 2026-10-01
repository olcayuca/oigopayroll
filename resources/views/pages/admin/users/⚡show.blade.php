<?php

use App\Enums\AuditEvent;
use App\Support\Audit;
use App\Actions\Access\GrantAccess;
use App\Actions\Users\ManageUser;
use App\Enums\ScopeType;
use App\Enums\UserType;
use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Firm;
use App\Models\PermissionTemplate;
use App\Models\User;
use App\Models\Workplace;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Kullanıcı')] class extends Component {
    public User $user;

    #[Url(as: 'sekme', except: 'yetkiler')]
    public string $tab = 'yetkiler';

    // Profile
    public string $name = '';

    public string $email = '';

    public string $type = '';

    // Grant editor
    public ?int $editingGrantId = null;

    public string $scopeType = 'firm';

    public string $firmId = '';

    public string $companyId = '';

    public string $workplaceId = '';

    public string $templateId = '';

    /** @var list<string> */
    public array $permissions = [];

    public ?string $newPassword = null;

    public function mount(User $user): void
    {
        $this->authorize('view', $user);

        $this->user = $user;
        $this->fillProfile();
    }

    /**
     * @return Collection<int, AccessGrant>
     */
    #[Computed]
    public function grants(): Collection
    {
        return $this->user->accessGrants()->with('template')->get();
    }

    /**
     * @return Collection<int, Firm>
     */
    #[Computed]
    public function firms(): Collection
    {
        return Firm::orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        return $this->firmId === ''
            ? new Collection
            : Company::where('firm_id', $this->firmId)->orderBy('title')->get(['id', 'title', 'company_no']);
    }

    /**
     * @return Collection<int, Workplace>
     */
    #[Computed]
    public function workplaces(): Collection
    {
        return $this->companyId === ''
            ? new Collection
            : Workplace::where('company_id', $this->companyId)->orderBy('branch_name')->get(['id', 'branch_name', 'workplace_no']);
    }

    public function updatedFirmId(): void
    {
        $this->reset('companyId', 'workplaceId');
    }

    public function updatedCompanyId(): void
    {
        $this->reset('workplaceId');
    }

    public function saveProfile(ManageUser $manageUser): void
    {
        $this->authorize('update', $this->user);

        $manageUser->update($this->user, ['name' => $this->name, 'email' => $this->email, 'type' => $this->type], Auth::user());

        Flux::toast(variant: 'success', text: 'Kullanıcı bilgileri güncellendi.');
    }

    public function toggleActive(ManageUser $manageUser): void
    {
        $this->authorize('update', $this->user);

        $manageUser->setActive($this->user, ! $this->user->is_active, Auth::user());

        Flux::toast(variant: 'success', text: $this->user->is_active ? 'Hesap aktifleştirildi.' : 'Hesap pasife alındı.');
    }

    /**
     * Open the panel as this user (destek görünümü) in a new tab.
     */
    public function impersonate(): void
    {
        try {
            $token = \App\Support\Impersonation::issue(auth()->user(), $this->user);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Flux::toast(variant: 'danger', text: collect($e->errors())->flatten()->first());

            return;
        }

        $this->js('window.open('.json_encode(\App\Enums\Portal::Panel->url('destek/'.$token)).', "_blank")');
    }

    public function resetPassword(ManageUser $manageUser): void
    {
        $this->authorize('update', $this->user);

        $this->newPassword = $manageUser->resetPassword($this->user);

        Flux::modal('temporary-password')->show();
    }

    public function newGrant(): void
    {
        $this->reset('editingGrantId', 'firmId', 'companyId', 'workplaceId', 'templateId', 'permissions');
        $this->scopeType = 'firm';
        $this->resetValidation();

        Flux::modal('grant')->show();
    }

    public function editGrant(int $grantId): void
    {
        $grant = $this->user->accessGrants()->findOrFail($grantId);
        $scope = $grant->scopeModel();

        $this->editingGrantId = $grant->id;
        $this->scopeType = $grant->scope_type->value;
        $this->firmId = (string) match (true) {
            $scope instanceof Workplace => $scope->company->firm_id,
            $scope instanceof Company => $scope->firm_id,
            default => $grant->scope_id,
        };
        $this->companyId = (string) match (true) {
            $scope instanceof Workplace => $scope->company_id,
            $scope instanceof Company => $scope->id,
            default => '',
        };
        $this->workplaceId = $scope instanceof Workplace ? (string) $scope->id : '';
        $this->templateId = (string) ($grant->permission_template_id ?? '');
        $this->permissions = $grant->permissions ?? [];
        $this->resetValidation();

        Flux::modal('grant')->show();
    }

    public function saveGrant(GrantAccess $grantAccess): void
    {
        $this->authorize('update', $this->user);

        $scopeType = ScopeType::from($this->scopeType);
        $key = match ($scopeType) {
            ScopeType::Firm => 'firmId',
            ScopeType::Company => 'companyId',
            ScopeType::Workplace => 'workplaceId',
        };

        $this->validate([$key => ['required']], [], ['firmId' => 'Firma', 'companyId' => 'Şirket', 'workplaceId' => 'İşyeri']);

        $scope = match ($scopeType) {
            ScopeType::Firm => Firm::findOrFail($this->firmId),
            ScopeType::Company => Company::findOrFail($this->companyId),
            ScopeType::Workplace => Workplace::findOrFail($this->workplaceId),
        };

        try {
            DB::transaction(function () use ($grantAccess, $scopeType, $scope) {
                // Moving an existing grant to another scope replaces it.
                if ($this->editingGrantId !== null) {
                    $existing = $this->user->accessGrants()->find($this->editingGrantId);

                    if ($existing && ($existing->scope_type !== $scopeType || $existing->scope_id !== $scope->getKey())) {
                        $existing->delete();
                    }
                }

                $grantAccess->handle(
                    $this->user,
                    $scope,
                    $this->permissions,
                    $this->templateId !== '' ? PermissionTemplate::find($this->templateId) : null,
                    Auth::user(),
                );
            });
        } catch (ValidationException $e) {
            // e.g. a client user of another firm: shown in the modal, nothing changed.
            $this->addError('grant', collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->grants);
        Flux::modal('grant')->close();
        Flux::toast(variant: 'success', text: 'Yetki kaydedildi.');
    }

    public function removeGrant(int $grantId): void
    {
        $this->authorize('update', $this->user);

        $grant = $this->user->accessGrants()->findOrFail($grantId);
        $label = $grant->scopeLabel();
        $grant->delete();
        Audit::log(AuditEvent::AccessRevoked, "{$this->user->name} kullanıcısının yetkisi kaldırıldı: {$label}", $this->user);
        $this->user->flushAccessCache();

        unset($this->grants);
        Flux::toast(variant: 'success', text: 'Yetki kaldırıldı.');
    }

    private function fillProfile(): void
    {
        $this->name = $this->user->name;
        $this->email = $this->user->email;
        $this->type = $this->user->type->value;
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('admin.users.index')" wire:navigate>Kullanıcılar</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $user->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl">{{ $user->name }}</flux:heading>
                <flux:badge :color="$user->is_active ? 'green' : 'zinc'">{{ $user->is_active ? 'Aktif' : 'Pasif' }}</flux:badge>
            </div>
            <flux:text class="mt-1">
                {{ $user->type->label() }} · {{ $user->email }}
                @if ($user->type->isClient())
                    · Ait olduğu firma:
                    @if ($user->homeFirm)
                        <a href="{{ route('admin.firms.show', $user->homeFirm) }}" wire:navigate class="underline">{{ $user->homeFirm->name }}</a>
                    @else
                        henüz yok (ilk yetkiyle belirlenir)
                    @endif
                @endif
            </flux:text>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($user->is_active && $user->type !== \App\Enums\UserType::SuperAdmin)
                <flux:button icon="eye" wire:click="impersonate"
                    wire:confirm="Panel, {{ $user->name }} olarak yeni sekmede açılacak (en fazla {{ \App\Support\Impersonation::MAX_MINUTES }} dakika). Yapılan her işlem sizin adınızla kayda geçer. Devam edilsin mi?">Kullanıcı olarak görüntüle</flux:button>
            @endif
            <flux:button icon="key" wire:click="resetPassword"
                wire:confirm="Yeni bir geçici şifre oluşturulsun mu? Mevcut şifre geçersiz olur.">Şifre Sıfırla</flux:button>
            @if ($user->is_active)
                <flux:button icon="pause-circle" wire:click="toggleActive" wire:confirm="Hesap pasife alınsın mı? Kullanıcı giriş yapamaz.">Pasife Al</flux:button>
            @else
                <flux:button icon="play-circle" wire:click="toggleActive">Aktifleştir</flux:button>
            @endif
        </div>
    </div>

    <x-tabs :active="$tab" :tabs="['yetkiler' => 'Yetkiler', 'hesap' => 'Hesap Bilgileri']"
        :counts="$user->type === UserType::SuperAdmin ? [] : ['yetkiler' => $this->grants->count()]"
        :invalid="$errors->hasAny(['name', 'email', 'type']) ? ['hesap'] : []" />

    @if ($tab === 'hesap')
    <section class="max-w-2xl rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <form wire:submit="saveProfile" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" label="Ad Soyad" required />
                <flux:input wire:model="email" type="email" label="E-posta" required />
            </div>
            <flux:select wire:model="type" label="Kullanıcı Tipi">
                @foreach (UserType::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    </section>
    @endif

    @if ($tab === 'yetkiler')
    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:text>Kullanıcının firma, şirket ve işyeri düzeyindeki yetkileri.</flux:text>
            @if ($user->type !== UserType::SuperAdmin)
                <flux:button size="sm" icon="plus" wire:click="newGrant">Yetki Ekle</flux:button>
            @endif
        </div>

        @if ($user->type === UserType::SuperAdmin)
            <flux:callout icon="shield-check" heading="Süper admin tüm firmalarda tüm yetkilere sahiptir."
                text="Ayrıca yetki tanımlamaya gerek yoktur." />
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Kapsam</flux:table.column>
                    <flux:table.column>Seviye</flux:table.column>
                    <flux:table.column>Yetkiler</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->grants as $grant)
                        <flux:table.row :key="$grant->id">
                            <flux:table.cell variant="strong">{{ $grant->scopeLabel() }}</flux:table.cell>
                            <flux:table.cell>{{ $grant->scope_type->label() }}</flux:table.cell>
                            <flux:table.cell class="max-w-md whitespace-normal">
                                @if ($grant->template)
                                    <flux:badge size="sm" color="indigo">{{ $grant->template->name }}</flux:badge>
                                @endif
                                <span class="text-xs text-zinc-500">
                                    {{ collect($grant->effectivePermissions())->map(fn ($p) => \App\Enums\Permission::from($p)->label())->join(', ') }}
                                </span>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="whitespace-nowrap">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editGrant({{ $grant->id }})" />
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeGrant({{ $grant->id }})" wire:confirm="Bu yetki kaldırılsın mı?" />
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">
                                Bu kullanıcının hiçbir firmada yetkisi yok; panelde işlem yapamaz.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif
    </section>

    @endif

    <flux:modal name="grant" class="md:w-[44rem]">
        <form wire:submit="saveGrant" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingGrantId ? 'Yetkiyi Düzenle' : 'Yetki Ekle' }}</flux:heading>
                <flux:text class="mt-1">Firma yetkisi o firmanın tüm şirket ve işyerlerini, şirket yetkisi tüm işyerlerini kapsar.</flux:text>
            </div>

            <flux:radio.group wire:model.live="scopeType" label="Seviye" variant="segmented">
                @foreach (ScopeType::cases() as $case)
                    <flux:radio value="{{ $case->value }}" label="{{ $case->label() }}" />
                @endforeach
            </flux:radio.group>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:select wire:model.live="firmId" label="Firma">
                    <flux:select.option value="">Seçiniz</flux:select.option>
                    @foreach ($this->firms as $firm)
                        <flux:select.option value="{{ $firm->id }}">{{ $firm->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($scopeType !== 'firm')
                    <flux:select wire:model.live="companyId" label="Şirket" :disabled="$firmId === ''">
                        <flux:select.option value="">Seçiniz</flux:select.option>
                        @foreach ($this->companies as $company)
                            <flux:select.option value="{{ $company->id }}">{{ $company->company_no }} · {{ $company->title }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                @if ($scopeType === 'workplace')
                    <flux:select wire:model="workplaceId" label="İşyeri" :disabled="$companyId === ''">
                        <flux:select.option value="">Seçiniz</flux:select.option>
                        @foreach ($this->workplaces as $workplace)
                            <flux:select.option value="{{ $workplace->id }}">{{ $workplace->workplace_no }} · {{ $workplace->branch_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </div>

            <x-permission-picker />

            @error('grant') <flux:callout icon="exclamation-triangle" color="red" :heading="$message" /> @enderror

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    </flux:modal>

    <x-temporary-password-modal :email="$user->email" :password="$newPassword" />
</div>
