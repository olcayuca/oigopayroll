<?php

namespace App\Assistant;

use App\Enums\DefinitionType;
use App\Models\Company;
use App\Models\Definition;
use App\Models\Employee;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use App\Validation\EmployeeRules;
use App\Validation\WorkplaceRules;

/**
 * The active firm's setup status for the assistant, limited to what the user may see.
 *
 * Deliberately free of personal data: no person names, TCKN, IBAN or credentials — companies,
 * workplace branch names, counts, sicil numbers and the labels of missing fields only.
 */
final class SetupContext
{
    public static function for(User $user, Firm $firm): string
    {
        $companies = Company::visibleTo($user)->where('firm_id', $firm->id)->withCount('workplaces')->orderBy('company_no')->get();
        $workplaces = Workplace::visibleTo($user)->whereIn('company_id', $companies->pluck('id'))->with('company:id,short_name')->get();
        $definitions = Definition::query()->toBase()->where('firm_id', $firm->id)->where('is_active', true)
            ->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');
        $employees = Employee::viewableBy($user, $firm)->get();

        $lines = ["Firma: {$firm->name} (durum: {$firm->status->label()})", 'Tarih: '.now()->format('d.m.Y')];

        $lines[] = "\nŞirketler ({$companies->count()}):";
        foreach ($companies as $company) {
            $lines[] = "- No {$company->company_no} · {$company->short_name} · {$company->workplaces_count} işyeri";
        }

        $incomplete = $workplaces->filter(fn (Workplace $workplace) => $workplace->setupPercent() < 100);
        $lines[] = "\nİşyerleri ({$workplaces->count()}, kurulum dosyası alanları eksik olan: {$incomplete->count()}):";
        foreach ($workplaces->sortBy(fn (Workplace $workplace) => $workplace->setupPercent())->take(15) as $workplace) {
            $missing = collect($workplace->missingSetupFields())
                ->map(fn (string $entry) => WorkplaceRules::attributes()[explode('|', $entry)[0]] ?? $entry)->implode(', ');
            $lines[] = "- {$workplace->company->short_name} / {$workplace->branch_name} (No {$workplace->workplace_no}): %{$workplace->setupPercent()}"
                .($missing !== '' ? " — eksik: {$missing}" : '');
        }

        $lines[] = "\nTanımlar (aktif): ".collect(DefinitionType::cases())
            ->map(fn (DefinitionType $type) => $type->label().' '.($definitions[$type->value] ?? 0))->implode(', ');

        $incompleteEmployees = $employees->filter(fn (Employee $employee) => $employee->missingRequiredFields() !== []);
        $lines[] = "\nPersonel: {$employees->count()} kayıt, ".$employees->where('status', Employee::ACTIVE)->count().' aktif, '
            .$incompleteEmployees->count().' kayıtta zorunlu alan eksik.';
        foreach ($incompleteEmployees->take(10) as $employee) {
            $missing = collect($employee->missingRequiredFields())->map(fn ($field) => EmployeeRules::attributes()[$field] ?? $field)->implode(', ');
            $lines[] = "- Sicil {$employee->registry_no}: eksik {$missing}";
        }

        return implode("\n", $lines);
    }
}
