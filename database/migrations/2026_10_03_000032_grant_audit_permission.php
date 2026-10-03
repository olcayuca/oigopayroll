<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "İşlem geçmişini görüntüleme" is new: firm user managers (firm.manage_users) get it on the same scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['access_grants', 'permission_templates'] as $table) {
            DB::table($table)->whereNotNull('permissions')->orderBy('id')->each(function (object $row) use ($table) {
                $permissions = json_decode((string) $row->permissions, true) ?: [];

                if (in_array('firm.manage_users', $permissions, true) && ! in_array('firm.view_audit', $permissions, true)) {
                    $permissions[] = 'firm.view_audit';
                    DB::table($table)->where('id', $row->id)->update(['permissions' => json_encode(array_values($permissions))]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['access_grants', 'permission_templates'] as $table) {
            DB::table($table)->whereNotNull('permissions')->orderBy('id')->each(function (object $row) use ($table) {
                $permissions = json_decode((string) $row->permissions, true) ?: [];
                DB::table($table)->where('id', $row->id)
                    ->update(['permissions' => json_encode(array_values(array_diff($permissions, ['firm.view_audit'])))]);
            });
        }
    }
};
