<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Personnel permissions are new: everyone who could do X on workplaces may do X on personnel.
 * Applies to existing access grants and permission templates.
 */
return new class extends Migration
{
    private const MAP = [
        'workplace.view' => 'employee.view',
        'workplace.create' => 'employee.create',
        'workplace.update' => 'employee.update',
        'workplace.delete' => 'employee.delete',
        'workplace.import' => 'employee.import',
    ];

    public function up(): void
    {
        foreach (['access_grants', 'permission_templates'] as $table) {
            DB::table($table)->whereNotNull('permissions')->orderBy('id')->each(function (object $row) use ($table) {
                $permissions = json_decode((string) $row->permissions, true) ?: [];
                $added = array_values(array_filter(array_map(fn ($p) => self::MAP[$p] ?? null, $permissions)));
                $merged = array_values(array_unique([...$permissions, ...$added]));

                if ($merged !== $permissions) {
                    DB::table($table)->where('id', $row->id)->update(['permissions' => json_encode($merged)]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['access_grants', 'permission_templates'] as $table) {
            DB::table($table)->whereNotNull('permissions')->orderBy('id')->each(function (object $row) use ($table) {
                $permissions = json_decode((string) $row->permissions, true) ?: [];
                $kept = array_values(array_filter($permissions, fn ($p) => ! str_starts_with((string) $p, 'employee.')));
                DB::table($table)->where('id', $row->id)->update(['permissions' => json_encode($kept)]);
            });
        }
    }
};
