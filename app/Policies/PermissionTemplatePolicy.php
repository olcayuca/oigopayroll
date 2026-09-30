<?php

namespace App\Policies;

use App\Models\PermissionTemplate;
use App\Models\User;

/**
 * Permission templates are managed by HRD Super Admins only (granted via Gate::before).
 */
class PermissionTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PermissionTemplate $template): bool
    {
        return false;
    }

    public function delete(User $user, PermissionTemplate $template): bool
    {
        return false;
    }
}
