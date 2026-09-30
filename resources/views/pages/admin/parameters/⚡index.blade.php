<?php

use App\Models\LegalParameter;
use App\Payroll\Parameters\LegalParameters;
use App\Payroll\Parameters\ParameterCatalog;
use App\Payroll\Parameters\ParameterDefinition;
use App\Payroll\Parameters\ParameterType;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Yasal Parametreler')] class extends Component {
    #[Url(as: 'sekme', except: 'ucret')]
    public string $tab = 'ucret';

    /** Show the values in force on this date. */
    #[Url(as: 'tarih')]
    public string $asOf = '';

    // Editor
    public ?string $editingKey = null;

    public string $effectiveFrom = '';

    public string $value = '';

    /** @var list<array{up_to: string|null, rate: string}> */
    public array $brackets = [];

    public string $source = '';

    public string $note = '';

    public function mount(): void
    {
        $this->authorize('manage-settings');
        $this->asOf = $this->asOf ?: now()->toDateString();
    }

    #[Computed]
    public function service(): LegalParameters
    {
        return app(LegalParameters::class);
    }

    /**
     * @return array<string, ParameterDefinition>
     */
    #[Computed]
    public function definitions(): array
    {
        return ParameterCatalog::inGroup($this->tab);
    }

    #[Computed]
    public function editing(): ?ParameterDefinition
    {
        return $this->editingKey ? ParameterCatalog::find($this->editingKey) : null;
    }

    /**
     * @return Collection<int, LegalParameter>
     */
    #[Computed]
    public function editingHistory(): Collection
    {
        return $this->editingKey ? $this->service->history($this->editingKey)->load('editor') : new Collection;
    }

    /**
     * Parameters without a value on the selected date (the payroll engine would stop on these).
     *
     * @return list<string>
     */
    #[Computed]
    public function missing(): array
    {
        return array_values(array_map(
            fn (ParameterDefinition $definition) => $definition->label,
            array_filter(ParameterCatalog::all(), fn (ParameterDefinition $definition) => $this->service->value($definition->key, $this->asOf) === null),
        ));
    }

    public function open(string $key): void
    {
        $definition = ParameterCatalog::find($key) ?? abort(404);
        $current = $this->service->entryAt($key, $this->asOf);

        $this->editingKey = $key;
        $this->effectiveFrom = now()->startOfMonth()->toDateString();
        $this->value = is_string($current?->value) ? $current->value : '';
        $this->brackets = $definition->type === ParameterType::Brackets && is_array($current?->value) ? $current->value : [['up_to' => '', 'rate' => '']];
        $this->source = '';
        $this->note = '';
        $this->resetValidation();
        unset($this->editingHistory);

        Flux::modal('parameter')->show();
    }

    public function editEntry(int $entryId): void
    {
        $entry = $this->editingHistory->firstWhere('id', $entryId) ?? abort(404);

        $this->effectiveFrom = $entry->effective_from->toDateString();
        $this->value = is_string($entry->value) ? $entry->value : '';
        $this->brackets = is_array($entry->value) ? $entry->value : $this->brackets;
        $this->source = (string) $entry->source;
        $this->note = (string) $entry->note;
    }

    public function addBracket(): void
    {
        $this->brackets[] = ['up_to' => '', 'rate' => ''];
    }

    public function removeBracket(int $index): void
    {
        unset($this->brackets[$index]);
        $this->brackets = array_values($this->brackets);
    }

    public function save(): void
    {
        $this->authorize('manage-settings');
        $definition = $this->editing ?? abort(404);

        $this->validate(['effectiveFrom' => ['required', 'date']], [], ['effectiveFrom' => 'Yürürlük tarihi']);

        try {
            $this->service->set(
                $definition->key,
                $this->effectiveFrom,
                $definition->type === ParameterType::Brackets ? $this->brackets : $this->value,
                $this->source,
                $this->note,
                Auth::user(),
            );
        } catch (ValidationException $e) {
            $this->addError('value', collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->editingHistory, $this->missing);
        Flux::toast(variant: 'success', text: "{$definition->label} kaydedildi.");
    }

    public function deleteEntry(int $entryId): void
    {
        $this->authorize('manage-settings');

        $entry = $this->editingHistory->firstWhere('id', $entryId) ?? abort(404);
        $this->service->delete($entry, Auth::user());

        unset($this->editingHistory, $this->missing);
        Flux::toast(variant: 'success', text: 'Dönem silindi.');
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Yasal Parametreler</flux:heading>
            <flux:text class="mt-1">
                Bordro hesaplamasında kullanılan yasal oran ve tutarlar. Her değer bir yürürlük tarihinden itibaren geçerlidir;
                yıl içindeki değişiklikler yeni dönem olarak eklenir, geçmiş bordrolar kendi tarihindeki değerle hesaplanır.
            </flux:text>
        </div>
        <div class="w-48">
            <flux:input wire:model.live="asOf" type="date" label="Geçerli değerler tarihi" />
        </div>
    </div>

    @if ($this->missing)
        <flux:callout icon="exclamation-triangle" color="amber" heading="{{ \Illuminate\Support\Carbon::parse($asOf)->format('d.m.Y') }} için tanımsız parametreler"
            text="{{ implode(', ', $this->missing) }}. Bu tarihteki bordrolar hesaplanamaz." />
    @endif

    <x-tabs :active="$tab" :tabs="ParameterCatalog::GROUPS"
        :counts="collect(ParameterCatalog::GROUPS)->map(fn ($label, $group) => count(ParameterCatalog::inGroup($group)))->all()" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Parametre</flux:table.column>
            <flux:table.column>Değer ({{ \Illuminate\Support\Carbon::parse($asOf)->format('d.m.Y') }})</flux:table.column>
            <flux:table.column>Yürürlük</flux:table.column>
            <flux:table.column>Kaynak</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->definitions as $definition)
                @php($entry = $this->service->entryAt($definition->key, $asOf))
                <flux:table.row :key="$definition->key">
                    <flux:table.cell variant="strong" class="whitespace-normal">
                        {{ $definition->label }}
                        @if ($definition->description) <div class="text-xs font-normal text-zinc-500">{{ $definition->description }}</div> @endif
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">
                        @if (! $entry)
                            <flux:badge size="sm" color="amber">Tanımsız</flux:badge>
                        @elseif ($definition->type === ParameterType::Brackets)
                            <div class="space-y-0.5 text-sm">
                                @foreach ($entry->value as $bracket)
                                    <div>
                                        {{ $bracket['up_to'] ? number_format((float) $bracket['up_to'], 0, ',', '.').' TL\'ye kadar' : 'Üzeri' }}:
                                        <strong>%{{ rtrim(rtrim(number_format((float) $bracket['rate'], 2, ',', '.'), '0'), ',') }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <strong>{{ $definition->format($entry->value) }}</strong>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">
                        {{ $entry?->effective_from->format('d.m.Y') ?? '—' }}
                        @php($count = $this->service->history($definition->key)->count())
                        @if ($count > 1) <div class="text-xs text-zinc-500">{{ $count }} dönem</div> @endif
                    </flux:table.cell>
                    <flux:table.cell class="max-w-xs whitespace-normal text-xs text-zinc-500">{{ $entry?->source }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="open('{{ $definition->key }}')">Dönemler</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="parameter" class="md:w-[48rem]">
        @if ($this->editing)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $this->editing->label }}</flux:heading>
                    @if ($this->editing->description) <flux:text class="mt-1">{{ $this->editing->description }}</flux:text> @endif
                </div>

                <div>
                    <flux:heading>Dönemler</flux:heading>
                    <flux:table class="mt-2">
                        <flux:table.columns>
                            <flux:table.column>Yürürlük</flux:table.column>
                            <flux:table.column>Değer</flux:table.column>
                            <flux:table.column>Kaynak</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse ($this->editingHistory as $entry)
                                <flux:table.row :key="'h-'.$entry->id">
                                    <flux:table.cell>{{ $entry->effective_from->format('d.m.Y') }}</flux:table.cell>
                                    <flux:table.cell>{{ $this->editing->format($entry->value) }}</flux:table.cell>
                                    <flux:table.cell class="max-w-xs whitespace-normal text-xs text-zinc-500">
                                        {{ $entry->source }}
                                        @if ($entry->editor) <div>{{ $entry->editor->name }}, {{ $entry->updated_at?->format('d.m.Y H:i') }}</div> @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="whitespace-nowrap">
                                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editEntry({{ $entry->id }})" />
                                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteEntry({{ $entry->id }})"
                                            wire:confirm="{{ $entry->effective_from->format('d.m.Y') }} dönemi silinsin mi?" />
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="4" class="py-6 text-center text-zinc-500">Henüz değer girilmemiş.</flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>

                <form wire:submit="save" class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading>Dönem ekle / düzelt</flux:heading>
                    <flux:text size="sm">Aynı yürürlük tarihli bir dönem varsa üzerine yazılır.</flux:text>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="effectiveFrom" type="date" label="Yürürlük tarihi" required />
                        @if ($this->editing->type !== ParameterType::Brackets)
                            <flux:input wire:model="value" type="number" step="any" min="0" required
                                :label="'Değer ('.$this->editing->type->unit().')'" />
                        @endif
                    </div>

                    @if ($this->editing->type === ParameterType::Brackets)
                        <div class="space-y-2">
                            <flux:label>Dilimler (yıllık kümülatif matrah)</flux:label>
                            @foreach ($brackets as $index => $bracket)
                                <div wire:key="bracket-{{ $index }}" class="flex items-end gap-2">
                                    <div class="flex-1">
                                        <flux:input wire:model="brackets.{{ $index }}.up_to" type="number" step="any" min="0"
                                            :placeholder="$loop->last ? 'Sınırsız (son dilim)' : 'Üst sınır (TL)'" :disabled="$loop->last" />
                                    </div>
                                    <div class="w-32">
                                        <flux:input wire:model="brackets.{{ $index }}.rate" type="number" step="any" min="0" max="100" placeholder="Oran %" />
                                    </div>
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeBracket({{ $index }})" :disabled="count($brackets) === 1" />
                                </div>
                            @endforeach
                            <flux:button size="sm" icon="plus" wire:click="addBracket">Dilim ekle</flux:button>
                        </div>
                    @endif

                    <flux:error name="value" />
                    <flux:input wire:model="source" label="Kaynak" placeholder="Ör. Resmî Gazete tarih/sayı, genelge, tebliğ" />
                    <flux:input wire:model="note" label="Not" />

                    <div class="flex justify-end gap-2">
                        <flux:modal.close><flux:button variant="filled">Kapat</flux:button></flux:modal.close>
                        <flux:button type="submit" variant="primary">Kaydet</flux:button>
                    </div>
                </form>
            </div>
        @endif
    </flux:modal>
</div>
