<?php

use App\Actions\Access\GrantAccess;
use App\Actions\Firms\ReviewFirm;
use App\Actions\Firms\UpdateFirm;
use App\Actions\Users\AddFirmUser;
use App\Enums\FirmStatus;
use App\Enums\ScopeType;
use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Firm;
use App\Models\PermissionTemplate;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Firma Detayı')] class extends Component {
    public Firm $firm;

    public string $rejectionReason = '';

    public string $name = '';

    // Add user
    public string $userEmail = '';

    public string $userName = '';

    public string $templateId = '';

    /** @var list<string> */
    public array $permissions = [];

    // One-time password display
    public ?string $createdEmail = null;

    public ?string $createdPassword = null;

    public function mount(Firm $firm): void
    {
        $this->authorize('view', $firm);

        $this->firm = $firm;
        $this->name = $firm->name;
        $this->templateId = (string) (PermissionTemplate::where('name', 'Tam Yetki')->value('id') ?? '');
    }

    /**
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        return Company::query()
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

    public function rename(UpdateFirm $updateFirm): void
    {
        $this->authorize('update', $this->firm);

        $updateFirm->update($this->firm, ['name' => $this->name]);

        Flux::modal('edit-firm')->close();
        Flux::toast(variant: 'success', text: 'Firma güncellendi.');
    }

    public function deactivate(UpdateFirm $updateFirm): void
    {
        $this->authorize('update', $this->firm);

        $updateFirm->deactivate($this->firm);

        Flux::toast(variant: 'success', text: 'Firma pasife alındı.');
    }

    public function reactivate(UpdateFirm $updateFirm): void
    {
        $this->authorize('update', $this->firm);

        $updateFirm->reactivate($this->firm);

        Flux::toast(variant: 'success', text: 'Firma yeniden aktif.');
    }

    public function addUser(AddFirmUser $addFirmUser): void
    {
        $this->authorize('manageUsers', $this->firm);

        $this->validate([
            'userEmail' => ['required', 'email', 'max:255'],
            'userName' => ['nullable', 'string', 'max:255'],
        ], [], ['userEmail' => 'E-posta', 'userName' => 'Ad Soyad']);

        $result = $addFirmUser->handle(
            $this->firm,
            $this->userEmail,
            $this->userName,
            $this->permissions,
            $this->templateId !== '' ? PermissionTemplate::find($this->templateId) : null,
            Auth::user(),
        );

        $this->reset('userEmail', 'userName', 'permissions');
        unset($this->grants);
        Flux::modal('add-user')->close();

        if ($result['password'] !== null) {
            $this->createdEmail = $result['user']->email;
            $this->createdPassword = $result['password'];
            Flux::modal('temporary-password')->show();
        } else {
            Flux::toast(variant: 'success', text: "{$result['user']->name} bu firmaya yetkilendirildi.");
        }
    }

    public function removeUser(int $grantId, GrantAccess $grantAccess): void
    {
        $this->authorize('manageUsers', $this->firm);

        $grant = AccessGrant::where('scope_type', ScopeType::Firm)->where('scope_id', $this->firm->id)->findOrFail($grantId);
        $grantAccess->revoke($grant->user, $this->firm);

        unset($this->grants);
        Flux::toast(variant: 'success', text: 'Kullanıcının firma yetkisi kaldırıldı.');
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

        <div class="flex flex-wrap gap-2">
            @if ($firm->status === FirmStatus::Pending)
                @can('review', $firm)
                    <flux:button variant="primary" color="green" icon="check" wire:click="approve" wire:confirm="Firma onaylansın mı?">Onayla</flux:button>
                    <flux:modal.trigger name="reject-firm">
                        <flux:button variant="danger" icon="x-mark">Reddet</flux:button>
                    </flux:modal.trigger>
                @endcan
            @endif

            @can('update', $firm)
                <flux:modal.trigger name="edit-firm">
                    <flux:button icon="pencil-square">Düzenle</flux:button>
                </flux:modal.trigger>

                @if ($firm->status === FirmStatus::Active)
                    <flux:button icon="pause-circle" wire:click="deactivate"
                        wire:confirm="Firma pasife alınsın mı? Pasif firmada şirket/işyeri işlemleri yapılamaz.">Pasife Al</flux:button>
                @elseif ($firm->status === FirmStatus::Passive)
                    <flux:button icon="play-circle" wire:click="reactivate">Aktifleştir</flux:button>
                @endif
            @endcan
        </div>
    </div>

    @if ($firm->status === FirmStatus::Pending)
        <flux:callout icon="clock" color="amber" heading="Onay bekliyor"
            text="Firma onaylanana kadar şirket veya işyeri eklenemez." />
    @elseif ($firm->status === FirmStatus::Rejected)
        <flux:callout icon="x-circle" color="red" heading="Reddedildi"
            text="{{ $firm->rejection_reason }} — {{ $firm->reviewer?->name }}, {{ $firm->reviewed_at?->format('d.m.Y H:i') }}" />
    @elseif ($firm->status === FirmStatus::Passive)
        <flux:callout icon="pause-circle" heading="Pasif"
            text="Bu firmada şirket, işyeri ve bordro işlemleri yapılamaz." />
    @elseif ($firm->reviewed_at)
        <flux:text size="sm">Onaylayan: {{ $firm->reviewer?->name }}, {{ $firm->reviewed_at->format('d.m.Y H:i') }}</flux:text>
    @endif

    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Firma Kullanıcıları</flux:heading>
            @can('manageUsers', $firm)
                <flux:modal.trigger name="add-user">
                    <flux:button size="sm" icon="user-plus">Kullanıcı Ekle</flux:button>
                </flux:modal.trigger>
            @endcan
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Kullanıcı</flux:table.column>
                <flux:table.column>Tip</flux:table.column>
                <flux:table.column>Yetkiler</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->grants as $grant)
                    <flux:table.row :key="$grant->id">
                        <flux:table.cell variant="strong">
                            {{ $grant->user->name }}
                            @unless ($grant->user->is_active) <flux:badge size="sm" color="zinc" inset="top bottom">Pasif</flux:badge> @endunless
                            <div class="text-xs font-normal text-zinc-500">{{ $grant->user->email }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $grant->user->type->label() }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($grant->template)
                                <flux:badge size="sm" color="indigo" inset="top bottom">{{ $grant->template->name }}</flux:badge>
                            @endif
                            {{ count($grant->effectivePermissions()) }} yetki
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap">
                            <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('admin.users.show', $grant->user)" wire:navigate />
                            @can('manageUsers', $firm)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeUser({{ $grant->id }})"
                                    wire:confirm="{{ $grant->user->name }} kullanıcısının bu firmadaki yetkisi kaldırılsın mı?" />
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">Bu firmada yetkilendirilmiş kullanıcı yok.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </section>

    <section class="space-y-3">
        <flux:heading size="lg">Şirketler</flux:heading>

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
                        <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">
                            Bu firmaya ait şirket yok. Şirketler panelden eklenir.
                        </flux:table.cell>
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
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Reddet</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="edit-firm" class="md:w-[28rem]">
        <form wire:submit="rename" class="space-y-6">
            <flux:heading size="lg">Firmayı Düzenle</flux:heading>
            <flux:input wire:model="name" label="Firma Adı" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="add-user" class="md:w-[40rem]">
        <form wire:submit="addUser" class="space-y-6">
            <div>
                <flux:heading size="lg">Firmaya Kullanıcı Ekle</flux:heading>
                <flux:text class="mt-1">
                    E-posta sistemde kayıtlıysa mevcut kullanıcıya yetki verilir; değilse yeni müşteri kullanıcısı oluşturulur
                    ve geçici şifresi gösterilir.
                </flux:text>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="userEmail" type="email" label="E-posta" required />
                <flux:input wire:model="userName" label="Ad Soyad (yeni kullanıcı için)" />
            </div>

            <x-permission-picker />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Ekle</flux:button>
            </div>
        </form>
    </flux:modal>

    <x-temporary-password-modal :email="$createdEmail" :password="$createdPassword" />
</div>
