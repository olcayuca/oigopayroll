<?php

namespace App\Support;

use App\Models\Setting;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Admin-editable security settings (Admin → Güvenlik → Ayarlar), with safe defaults.
 */
final class SecuritySettings
{
    public const TWO_FACTOR_REQUIRED = 'security.admin_two_factor_required';

    public const IP_ALLOWLIST = 'security.admin_ip_allowlist';

    public const IDLE_MINUTES = 'security.admin_idle_minutes';

    public const ATTEMPTS_PER_MINUTE = 'security.login_attempts_per_minute';

    public const ATTEMPTS_PER_HOUR = 'security.login_attempts_per_hour';

    public const AUDIT_RETENTION_DAYS = 'security.audit_retention_days';

    /**
     * Super admins must have two-factor authentication before using the admin panel.
     */
    public static function adminTwoFactorRequired(): bool
    {
        return (bool) Setting::get(self::TWO_FACTOR_REQUIRED, false);
    }

    /**
     * IPs / CIDR ranges allowed to reach the admin panel. Empty = no restriction.
     *
     * @return list<string>
     */
    public static function adminIpAllowlist(): array
    {
        return self::parseIpList((string) Setting::get(self::IP_ALLOWLIST, ''));
    }

    public static function adminIpAllowed(?string $ip): bool
    {
        $allowlist = self::adminIpAllowlist();

        return $allowlist === [] || ($ip !== null && IpUtils::checkIp($ip, $allowlist));
    }

    /**
     * Minutes of inactivity after which an admin session ends. 0 = off.
     */
    public static function adminIdleMinutes(): int
    {
        return max(0, (int) Setting::get(self::IDLE_MINUTES, 30));
    }

    public static function loginAttemptsPerMinute(): int
    {
        return max(1, (int) Setting::get(self::ATTEMPTS_PER_MINUTE, 5));
    }

    public static function loginAttemptsPerHour(): int
    {
        return max(1, (int) Setting::get(self::ATTEMPTS_PER_HOUR, 30));
    }

    public static function auditRetentionDays(): int
    {
        return max(30, (int) Setting::get(self::AUDIT_RETENTION_DAYS, 365));
    }

    /**
     * Split a textarea of IPs / CIDRs (one per line, "#" comments allowed).
     *
     * @return list<string>
     */
    public static function parseIpList(string $text): array
    {
        $entries = [];

        foreach (preg_split('/[\r\n,]+/', $text) ?: [] as $line) {
            $entry = trim(explode('#', $line)[0]);

            if ($entry !== '') {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Entries that are neither a valid IP nor a valid CIDR range.
     *
     * @param  list<string>  $entries
     * @return list<string>
     */
    public static function invalidIpEntries(array $entries): array
    {
        return array_values(array_filter($entries, function (string $entry) {
            [$address, $mask] = array_pad(explode('/', $entry, 2), 2, null);

            if (filter_var($address, FILTER_VALIDATE_IP) === false) {
                return true;
            }

            if ($mask === null) {
                return false;
            }

            $max = str_contains($address, ':') ? 128 : 32;

            return ! ctype_digit($mask) || (int) $mask > $max;
        }));
    }
}
