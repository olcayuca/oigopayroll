<?php

namespace App\Policies;

use App\Models\Firm;
use App\Models\User;

/**
 * Tanımlar are managed per firm (FirmPolicy::manageDefinitions); this only adds the Excel import ability.
 */
class DefinitionPolicy
{
    /**
     * Usage: $user->can('import', [Definition::class, $firm])
     */
    public function import(User $user, Firm $firm): bool
    {
        return $user->can('manageDefinitions', $firm);
    }
}
