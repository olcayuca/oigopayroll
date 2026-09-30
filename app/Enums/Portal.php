<?php

namespace App\Enums;

use App\Models\User;

/**
 * The three sites served by this application, told apart by host name.
 */
enum Portal: string
{
    use HasLabels;

    case Landing = 'landing';
    case Panel = 'panel';
    case Admin = 'admin';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Landing => 'Web Sitesi',
            self::Panel => 'Müşteri Paneli',
            self::Admin => 'HRD Yönetim Paneli',
        };
    }

    /**
     * Resolve the portal for a host. Unknown hosts are treated as the landing site.
     */
    public static function fromHost(string $host): self
    {
        foreach ([self::Admin, self::Panel] as $portal) {
            if (strcasecmp($host, $portal->domain()) === 0) {
                return $portal;
            }
        }

        return self::Landing;
    }

    public function domain(): string
    {
        $domain = config("portals.{$this->value}");

        return is_string($domain) ? $domain : '';
    }

    /**
     * Get an absolute URL on this portal, keeping the current scheme.
     */
    public function url(string $path = '/'): string
    {
        $scheme = request()->getScheme() ?: 'https';

        return $scheme.'://'.$this->domain().'/'.ltrim($path, '/');
    }

    /**
     * Determine if the user may sign in to this portal.
     */
    public function admits(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        // Admin: system management (firms, users, website, settings), super admins only.
        // Panel: day-to-day work (companies, workplaces, payroll) for client users and HRD staff,
        // each limited to the firms they can see.
        return match ($this) {
            self::Admin => $user->type === UserType::SuperAdmin,
            self::Panel => true,
            self::Landing => false,
        };
    }

    /**
     * Determine if self-registration is available on this portal.
     */
    public function allowsRegistration(): bool
    {
        return $this === self::Panel;
    }
}
