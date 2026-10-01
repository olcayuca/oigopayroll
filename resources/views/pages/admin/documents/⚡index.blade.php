<?php

use App\Enums\DocumentType;
use App\Models\FirmDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Belge Takibi')] class extends Component {
    use WithPagination;

    #[Url(as: 'sekme')]
    public string $tab = 'suresi-dolan';

    #[Url(as: 'tur', except: '')]
    public string $type = '';

    public function mount(): void
    {
        $this->authorize('manage-settings');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return [
            'suresi-dolan' => FirmDocument::whereNotNull('valid_until')->whereDate('valid_until', '<', today())->count(),
            'yaklasan' => FirmDocument::whereDate('valid_until', '>=', today())
                ->whereDate('valid_until', '<=', today()->addDays(FirmDocument::EXPIRY_WARNING_DAYS))->count(),
            'tumu' => FirmDocument::count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, FirmDocument>
     */
    #[Computed]
    public function documents(): LengthAwarePaginator
    {
        return FirmDocument::query()->with(['firm', 'company'])
            ->when($this->tab === 'suresi-dolan', fn ($query) => $query->whereNotNull('valid_until')->whereDate('valid_until', '<', today()))
            ->when($this->tab === 'yaklasan', fn ($query) => $query->whereDate('valid_until', '>=', today())
                ->whereDate('valid_until', '<=', today()->addDays(FirmDocument::EXPIRY_WARNING_DAYS)))
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->when($this->tab === 'tumu', fn ($query) => $query->latest('id'), fn ($query) => $query->orderBy('valid_until'))
            ->paginate(25);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">Belge Takibi</flux:heading>
        <flux:text class="mt-1">Tüm firmaların belgeleri; süresi dolan ve {{ FirmDocument::EXPIRY_WARNING_DAYS }} gün içinde dolacak olanlar.</flux:text>
    </div>

    <x-tabs :active="$tab" :tabs="['suresi-dolan' => 'Süresi Dolan', 'yaklasan' => 'Yaklaşan', 'tumu' => 'Tüm Belgeler']" :counts="$this->counts" />

    <flux:select wire:model.live="type" class="max-w-xs">
        <flux:select.option value="">Tüm belge türleri</flux:select.option>
        @foreach (DocumentType::cases() as $option)
            <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:table :paginate="$this->documents">
        <flux:table.columns>
            <flux:table.column>Firma</flux:table.column>
            <flux:table.column>Belge</flux:table.column>
            <flux:table.column>Şirket</flux:table.column>
            <flux:table.column>Geçerlilik</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->documents as $document)
                <flux:table.row :key="$document->id">
                    <flux:table.cell>
                        <a href="{{ route('admin.firms.show', ['firm' => $document->firm, 'sekme' => 'belgeler']) }}" wire:navigate class="font-medium hover:underline">{{ $document->firm->name }}</a>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div>{{ $document->title }}</div>
                        <div class="text-xs text-zinc-500">{{ $document->type->label() }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $document->company->short_name ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">
                        @php($validity = $document->validity())
                        @if ($validity === FirmDocument::EXPIRED)
                            <flux:badge size="sm" color="red" inset="top bottom">{{ $document->valid_until?->format('d.m.Y') }} · {{ $document->valid_until?->diffForHumans() }}</flux:badge>
                        @elseif ($validity === FirmDocument::EXPIRING)
                            <flux:badge size="sm" color="amber" inset="top bottom">{{ $document->valid_until?->format('d.m.Y') }} · {{ $document->valid_until?->diffForHumans() }}</flux:badge>
                        @else
                            {{ $document->valid_until?->format('d.m.Y') ?? 'Süresiz' }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('documents.download', $document)" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-10 text-center text-zinc-500">Belge yok.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
