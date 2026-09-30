<?php

use App\Actions\Users\ManageUser;
use App\Enums\UserType;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Kullanıcılar')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $state = '';

    public string $name = '';

    public string $email = '';

    public string $newType = 'payroll_specialist';

    public ?int $createdId = null;

    public ?string $createdEmail = null;

    public ?string $createdPassword = null;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'state'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->withCount('accessGrants')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')))
            ->when(UserType::tryFrom($this->type), fn ($query, $type) => $query->where('type', $type))
            ->when($this->state !== '', fn ($query) => $query->where('is_active', $this->state === 'active'))
            ->orderBy('name')
            ->paginate(20);
    }

    public function createUser(ManageUser $manageUser): void
    {
        $this->authorize('create', User::class);

        ['user' => $user, 'password' => $password] = $manageUser->create([
            'name' => $this->name, 'email' => $this->email, 'type' => $this->newType,
        ]);

        $this->reset('name', 'email');
        Flux::modal('create-user')->close();

        $this->createdId = $user->id;
        $this->createdEmail = $user->email;
        $this->createdPassword = $password;
        Flux::modal('temporary-password')->show();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Kullanıcılar</flux:heading>
            <flux:text class="mt-1">HRD personeli ve müşteri kullanıcıları, hesap durumları ve yetkileri.</flux:text>
        </div>

        <flux:modal.trigger name="create-user">
            <flux:button variant="primary" icon="user-plus">Yeni Kullanıcı</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Ad veya e-posta ara..." clearable />
        </div>
        <div class="w-48">
            <flux:select wire:model.live="type">
                <flux:select.option value="">Tüm tipler</flux:select.option>
                @foreach (\App\Enums\UserType::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="w-40">
            <flux:select wire:model.live="state">
                <flux:select.option value="">Tüm durumlar</flux:select.option>
                <flux:select.option value="active">Aktif</flux:select.option>
                <flux:select.option value="passive">Pasif</flux:select.option>
            </flux:select>
        </div>
    </div>

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column>Kullanıcı</flux:table.column>
            <flux:table.column>Tip</flux:table.column>
            <flux:table.column>Durum</flux:table.column>
            <flux:table.column align="end">Yetki kaydı</flux:table.column>
            <flux:table.column>Oluşturulma</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell variant="strong">
                        <a href="{{ route('admin.users.show', $user) }}" wire:navigate class="hover:underline">{{ $user->name }}</a>
                        <div class="text-xs font-normal text-zinc-500">{{ $user->email }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->type->label() }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$user->is_active ? 'green' : 'zinc'" inset="top bottom">{{ $user->is_active ? 'Aktif' : 'Pasif' }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        {{ $user->type === \App\Enums\UserType::SuperAdmin ? 'Tümü' : $user->access_grants_count }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->created_at?->format('d.m.Y') }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" icon="chevron-right" :href="route('admin.users.show', $user)" wire:navigate />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500">Kullanıcı bulunamadı.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="create-user" class="md:w-[28rem]">
        <form wire:submit="createUser" class="space-y-6">
            <div>
                <flux:heading size="lg">Yeni Kullanıcı</flux:heading>
                <flux:text class="mt-1">Kullanıcı geçici bir şifreyle oluşturulur. Yetkilerini sonraki ekranda verebilirsiniz.</flux:text>
            </div>

            <flux:input wire:model="name" label="Ad Soyad" required />
            <flux:input wire:model="email" type="email" label="E-posta" required />
            <flux:select wire:model="newType" label="Kullanıcı Tipi">
                @foreach (\App\Enums\UserType::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Oluştur</flux:button>
            </div>
        </form>
    </flux:modal>

    <x-temporary-password-modal :email="$createdEmail" :password="$createdPassword"
        :next="$createdId ? route('admin.users.show', $createdId) : null" next-label="Yetkileri Düzenle" />
</div>
