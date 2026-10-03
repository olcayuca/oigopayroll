<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Models\PermissionTemplate;
use Illuminate\Database\Seeder;

/**
 * Example permission templates. Templates are optional; permissions can always be granted one by one.
 */
class PermissionTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            'Tam Yetki' => [
                'Firma kapsamındaki tüm işlemler.',
                Permission::cases(),
            ],
            'Bordro Uzmanı' => [
                'Şirket/işyeri yönetimi ve bordro işlemleri; kullanıcı yönetimi hariç.',
                array_values(array_filter(Permission::cases(), fn (Permission $p) => ! in_array($p, [
                    Permission::FirmManageUsers, Permission::CompanyDelete, Permission::WorkplaceDelete, Permission::EmployeeDelete,
                ], true))),
            ],
            'Sadece Görüntüleme' => [
                'Kayıtları ve bordroları görüntüleyip indirebilir.',
                [Permission::FirmView, Permission::CompanyView, Permission::WorkplaceView, Permission::EmployeeView, Permission::PayrollView, Permission::PayrollDownload],
            ],
        ];

        foreach ($templates as $name => [$description, $permissions]) {
            PermissionTemplate::updateOrCreate(
                ['name' => $name],
                ['description' => $description, 'permissions' => Permission::sanitize($permissions)],
            );
        }
    }
}
