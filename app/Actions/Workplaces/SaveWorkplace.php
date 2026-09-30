<?php

namespace App\Actions\Workplaces;

use App\Models\Company;
use App\Models\User;
use App\Models\Workplace;
use App\Validation\WorkplaceInput;
use App\Validation\WorkplaceRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Create or update a workplace. Used by both the manual form and the Excel import.
 *
 * Authorization is the caller's job (WorkplacePolicy); this enforces the business rules.
 */
class SaveWorkplace
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(Company $company, array $input, ?User $user = null): Workplace
    {
        if (! $company->firm->isActive()) {
            throw ValidationException::withMessages(['firm' => 'İşyeri yalnızca aktif firmaların şirketlerine eklenebilir.']);
        }

        $data = $this->validate($input, $company);

        return $company->workplaces()->create([...$data, 'created_by' => $user?->id]);
    }

    /**
     * Blank credential fields keep their stored values.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(Workplace $workplace, array $input): Workplace
    {
        foreach (Workplace::SECRET_FIELDS as $field) {
            if (array_key_exists($field, $input) && ($input[$field] === null || $input[$field] === '')) {
                unset($input[$field]);
            }
        }

        $workplace->update($this->validate($input, $workplace->company, $workplace));

        return $workplace;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(array $input, Company $company, ?Workplace $ignore = null): array
    {
        $input = WorkplaceInput::normalize($input);

        return Validator::make(
            $input,
            WorkplaceRules::rules($input, $company, $ignore, updating: $ignore !== null),
            [],
            WorkplaceRules::attributes(),
        )->validate();
    }
}
