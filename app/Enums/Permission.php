<?php

namespace App\Enums;

/**
 * Operation-level permissions granted to users on a firm, company or workplace.
 *
 * Permissions are assigned individually or through an optional PermissionTemplate.
 * HRD Super Admins implicitly hold every permission everywhere.
 */
enum Permission: string
{
    use HasLabels;

    case FirmView = 'firm.view';
    case FirmUpdate = 'firm.update';
    case FirmManageUsers = 'firm.manage_users';

    case CompanyView = 'company.view';
    case CompanyCreate = 'company.create';
    case CompanyUpdate = 'company.update';
    case CompanyDelete = 'company.delete';
    case CompanyImport = 'company.import';

    case WorkplaceView = 'workplace.view';
    case WorkplaceCreate = 'workplace.create';
    case WorkplaceUpdate = 'workplace.update';
    case WorkplaceDelete = 'workplace.delete';
    case WorkplaceImport = 'workplace.import';
    case WorkplaceViewCredentials = 'workplace.view_credentials';

    // Payroll operations; enforced once the payroll module is built.
    case PayrollView = 'payroll.view';
    case PayrollDownload = 'payroll.download';
    case PayrollEnter = 'payroll.enter';
    case PayrollEdit = 'payroll.edit';
    case PayrollApprove = 'payroll.approve';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::FirmView => 'Firma görüntüleme',
            self::FirmUpdate => 'Firma düzenleme',
            self::FirmManageUsers => 'Firma kullanıcılarını yönetme',
            self::CompanyView => 'Şirket görüntüleme',
            self::CompanyCreate => 'Şirket oluşturma',
            self::CompanyUpdate => 'Şirket düzenleme',
            self::CompanyDelete => 'Şirket silme',
            self::CompanyImport => 'Excel ile şirket aktarma',
            self::WorkplaceView => 'İşyeri görüntüleme',
            self::WorkplaceCreate => 'İşyeri oluşturma',
            self::WorkplaceUpdate => 'İşyeri düzenleme',
            self::WorkplaceDelete => 'İşyeri silme',
            self::WorkplaceImport => 'Excel ile işyeri aktarma',
            self::WorkplaceViewCredentials => 'İşyeri şifrelerini görüntüleme',
            self::PayrollView => 'Bordro görüntüleme',
            self::PayrollDownload => 'Bordro indirme',
            self::PayrollEnter => 'Bordro veri girişi',
            self::PayrollEdit => 'Bordro düzenleme',
            self::PayrollApprove => 'Bordro onaylama',
        };
    }

    /**
     * Permissions grouped by area, for pickers.
     *
     * @return array<string, list<self>>
     */
    public static function groups(): array
    {
        $groups = ['firm' => 'Firma', 'company' => 'Şirket', 'workplace' => 'İşyeri', 'payroll' => 'Bordro'];
        $grouped = [];

        foreach (self::cases() as $case) {
            $grouped[$groups[explode('.', $case->value)[0]]][] = $case;
        }

        return $grouped;
    }

    /**
     * Permissions a client gets on a firm they registered themselves.
     *
     * @return list<self>
     */
    public static function firmOwnerDefaults(): array
    {
        return self::cases();
    }

    /**
     * Normalise a list of permission values, dropping unknown entries.
     *
     * @param  iterable<mixed>  $values
     * @return list<string>
     */
    public static function sanitize(iterable $values): array
    {
        $valid = [];

        foreach ($values as $value) {
            $case = $value instanceof self ? $value : (is_string($value) ? self::tryFrom($value) : null);

            if ($case !== null) {
                $valid[$case->value] = $case->value;
            }
        }

        return array_values($valid);
    }
}
