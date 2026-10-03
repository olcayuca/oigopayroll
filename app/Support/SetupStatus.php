<?php

namespace App\Support;

use App\Enums\DefinitionType;
use App\Models\Company;
use App\Models\Definition;
use App\Models\Employee;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Database\Eloquent\Collection;

/**
 * A firm's setup progress as the user sees it (Kurulum Sihirbazı, kurulum onayı):
 * 1 şirket · 2 işyeri (Firma Bilgileri) · 3 tanımlar · 4 personel (Personel Bilgileri) · 5 özet.
 */
final class SetupStatus
{
    /** Definition types personnel records cannot do without. */
    public const REQUIRED_DEFINITIONS = [DefinitionType::UpperUnit, DefinitionType::Title, DefinitionType::Position];

    /** @var Collection<int, Company>|null */
    private ?Collection $companies = null;

    /** @var Collection<int, Workplace>|null */
    private ?Collection $workplaces = null;

    /** @var array<string, int>|null */
    private ?array $definitionCounts = null;

    /** @var array{total: int, active: int, incomplete: int}|null */
    private ?array $personnel = null;

    public function __construct(private readonly User $user, private readonly Firm $firm) {}

    /**
     * @return Collection<int, Company>
     */
    public function companies(): Collection
    {
        return $this->companies ??= Company::visibleTo($this->user)->where('firm_id', $this->firm->id)->withCount('workplaces')->orderBy('company_no')->get();
    }

    /**
     * @return Collection<int, Workplace>
     */
    public function workplaces(): Collection
    {
        return $this->workplaces ??= Workplace::visibleTo($this->user)->whereIn('company_id', $this->companies()->pluck('id'))->with('company:id,short_name')->get();
    }

    /**
     * Active definitions per type value.
     *
     * @return array<string, int>
     */
    public function definitionCounts(): array
    {
        return $this->definitionCounts ??= Definition::query()->where('firm_id', $this->firm->id)->where('is_active', true)
            ->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type')->map(fn ($total) => (int) $total)->all();
    }

    /**
     * @return array{total: int, active: int, incomplete: int}
     */
    public function personnel(): array
    {
        $query = fn () => Employee::viewableBy($this->user, $this->firm);

        return $this->personnel ??= [
            'total' => $query()->count(),
            'active' => $query()->where('status', Employee::ACTIVE)->count(),
            'incomplete' => $query()->where('status', Employee::ACTIVE)->incomplete()->count(),
        ];
    }

    /**
     * Step => [done, status text].
     *
     * @return array<int, array{0: bool, 1: string}>
     */
    public function steps(): array
    {
        $withoutWorkplace = $this->companies()->where('workplaces_count', 0)->count();
        $incompleteWorkplaces = $this->workplaces()->filter(fn (Workplace $workplace) => $workplace->setupPercent() < 100)->count();
        $missingDefinitions = array_filter(self::REQUIRED_DEFINITIONS, fn (DefinitionType $type) => ($this->definitionCounts()[$type->value] ?? 0) === 0);
        $personnel = $this->personnel();

        $steps = [
            1 => [$this->companies()->isNotEmpty(), $this->companies()->count().' şirket'],
            2 => [$this->workplaces()->isNotEmpty() && $withoutWorkplace === 0 && $incompleteWorkplaces === 0,
                $this->workplaces()->count().' işyeri'.($incompleteWorkplaces ? ' · '.$incompleteWorkplaces.' eksik' : '')],
            3 => [$missingDefinitions === [], array_sum($this->definitionCounts()).' tanım'],
            4 => [$personnel['active'] > 0 && $personnel['incomplete'] === 0,
                $personnel['active'].' personel'.($personnel['incomplete'] ? ' · '.$personnel['incomplete'].' eksik' : '')],
        ];
        $steps[5] = [collect($steps)->every(fn ($item) => $item[0]), collect($steps)->filter(fn ($item) => $item[0])->count().' / 4 adım'];

        return $steps;
    }

    public function complete(): bool
    {
        return $this->steps()[5][0];
    }
}
