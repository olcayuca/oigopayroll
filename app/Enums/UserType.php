<?php

namespace App\Enums;

/**
 * Admin portal user types. Employees are a separate model with their own guard.
 */
enum UserType: string
{
    use HasLabels;

    case SuperAdmin = 'super_admin';
    case PayrollSpecialist = 'payroll_specialist';
    case ClientUser = 'client_user';

    public function isClient(): bool
    {
        return $this === self::ClientUser;
    }

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'HRD Super Admin',
            self::PayrollSpecialist => 'HRD Bordro Uzmanı',
            self::ClientUser => 'Müşteri Firma Kullanıcısı',
        };
    }
}
