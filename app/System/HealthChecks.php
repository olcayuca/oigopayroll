<?php

namespace App\System;

use App\Enums\UserType;
use App\Models\User;
use App\Support\SecuritySettings;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * System health checks for Admin → Sistem Sağlığı.
 */
class HealthChecks
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    public const SCHEDULER_HEARTBEAT = 'system.scheduler_heartbeat';

    public const MIN_PHP = '8.4.0';

    public function __construct(private readonly Backups $backups) {}

    /**
     * @return array<string, list<array{label: string, status: string, value: string, hint: string|null}>>
     */
    public function run(): array
    {
        return [
            'Uygulama' => $this->application(),
            'Güvenlik' => $this->security(),
            'Veritabanı' => $this->database(),
            'Depolama' => $this->storage(),
            'Arka plan işleri' => $this->background(),
            'E-posta ve yedekleme' => $this->mailAndBackups(),
        ];
    }

    /**
     * Worst status across all checks.
     *
     * @param  array<string, list<array{label: string, status: string, value: string, hint: string|null}>>  $results
     */
    public static function overall(array $results): string
    {
        $statuses = collect($results)->flatten(1)->pluck('status');

        return $statuses->contains(self::FAIL) ? self::FAIL : ($statuses->contains(self::WARN) ? self::WARN : self::OK);
    }

    /**
     * @return list<array{label: string, status: string, value: string, hint: string|null}>
     */
    private function application(): array
    {
        $production = app()->isProduction();

        return [
            self::check('PHP sürümü', version_compare(PHP_VERSION, self::MIN_PHP, '>=') ? self::OK : self::FAIL, PHP_VERSION,
                'Sistem en az PHP '.self::MIN_PHP.' gerektirir.'),
            self::check('Laravel sürümü', self::OK, app()->version()),
            self::check('Ortam', $production ? self::OK : self::WARN, (string) app()->environment(),
                $production ? null : 'Canlı sunucuda APP_ENV=production olmalıdır.'),
            self::check('Hata ayıklama modu', config('app.debug') ? ($production ? self::FAIL : self::WARN) : self::OK,
                config('app.debug') ? 'Açık' : 'Kapalı', config('app.debug') ? 'Canlıda APP_DEBUG=false olmalıdır; açıkken hata sayfaları iç bilgileri gösterir.' : null),
            self::check('Uygulama anahtarı', filled(config('app.key')) ? self::OK : self::FAIL, filled(config('app.key')) ? 'Tanımlı' : 'Yok',
                'APP_KEY şifreli alanları korur; kaybolursa işyeri şifreleri çözülemez. Güvenli bir yerde yedekleyin.'),
        ];
    }

    /**
     * @return list<array{label: string, status: string, value: string, hint: string|null}>
     */
    private function security(): array
    {
        $adminsWithoutTwoFactor = User::query()->where('type', UserType::SuperAdmin)->where('is_active', true)
            ->whereNull('two_factor_confirmed_at')->count();
        $secureCookie = (bool) config('session.secure');

        return [
            self::check('HTTPS çerezleri', $secureCookie ? self::OK : self::WARN, $secureCookie ? 'Açık' : 'Kapalı',
                $secureCookie ? null : 'SESSION_SECURE_COOKIE=true olmalıdır.'),
            self::check('Admin için iki adımlı doğrulama zorunlu', SecuritySettings::adminTwoFactorRequired() ? self::OK : self::WARN,
                SecuritySettings::adminTwoFactorRequired() ? 'Evet' : 'Hayır', 'Admin → Güvenlik → Ayarlar.'),
            self::check('İki adımlı doğrulaması olmayan süper admin', $adminsWithoutTwoFactor === 0 ? self::OK : self::WARN,
                (string) $adminsWithoutTwoFactor),
            self::check('Admin IP kısıtı', SecuritySettings::adminIpAllowlist() === [] ? self::WARN : self::OK,
                SecuritySettings::adminIpAllowlist() === [] ? 'Tanımlı değil' : count(SecuritySettings::adminIpAllowlist()).' adres',
                'Önerilir: yönetim panelini yalnızca ofis / VPN IP adreslerine açın.'),
        ];
    }

    /**
     * @return list<array{label: string, status: string, value: string, hint: string|null}>
     */
    private function database(): array
    {
        try {
            $connection = DB::connection();
            $connection->getPdo();
        } catch (Throwable $e) {
            return [self::check('Bağlantı', self::FAIL, 'Bağlanılamadı', mb_substr($e->getMessage(), 0, 200))];
        }

        $driver = $connection->getDriverName();
        $checks = [self::check('Bağlantı', self::OK, $driver.' '.$this->databaseVersion())];

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $bytes = (int) DB::table('information_schema.tables')->where('table_schema', $connection->getDatabaseName())
                ->sum(DB::raw('data_length + index_length'));
            $checks[] = self::check('Veritabanı boyutu', self::OK, self::bytes($bytes));
        }

        $pending = $this->pendingMigrations();
        $checks[] = self::check('Bekleyen veritabanı güncellemesi', $pending === 0 ? self::OK : self::FAIL, (string) $pending,
            $pending === 0 ? null : 'Sunucuda "php artisan migrate --force" çalıştırılmalı.');

        return $checks;
    }

    /**
     * @return list<array{label: string, status: string, value: string, hint: string|null}>
     */
    private function storage(): array
    {
        $free = @disk_free_space(storage_path());
        $freeStatus = $free === false ? self::WARN : ($free < 1024 ** 3 ? self::FAIL : ($free < 5 * 1024 ** 3 ? self::WARN : self::OK));
        $log = storage_path('logs/laravel.log');
        $logSize = is_file($log) ? (int) filesize($log) : 0;

        return [
            self::check('Depolama yazılabilir', is_writable(storage_path()) ? self::OK : self::FAIL, is_writable(storage_path()) ? 'Evet' : 'Hayır'),
            self::check('Boş disk alanı', $freeStatus, $free === false ? 'Okunamadı' : self::bytes((int) $free)),
            self::check('Günlük dosyası', $logSize > 100 * 1024 ** 2 ? self::WARN : self::OK, self::bytes($logSize),
                $logSize > 100 * 1024 ** 2 ? 'Günlük dosyası büyüdü; LOG_STACK=daily ile günlük döndürme önerilir.' : null),
        ];
    }

    /**
     * @return list<array{label: string, status: string, value: string, hint: string|null}>
     */
    private function background(): array
    {
        $heartbeat = Cache::get(self::SCHEDULER_HEARTBEAT);
        $lastRun = is_int($heartbeat) ? Carbon::createFromTimestamp($heartbeat) : null;
        $schedulerStatus = $lastRun !== null && $lastRun->gt(now()->subMinutes(10)) ? self::OK : self::FAIL;

        $checks = [
            self::check('Zamanlayıcı', $schedulerStatus, $lastRun ? 'Son çalışma '.$lastRun->diffForHumans() : 'Hiç çalışmadı',
                $schedulerStatus === self::OK ? null : 'Sunucuda her dakika "php artisan schedule:run" çalışmalı (cron / Görev Zamanlayıcı). Yedekleme ve kayıt temizliği buna bağlı.'),
        ];

        if (Schema::hasTable('jobs')) {
            $pending = DB::table('jobs')->count();
            $stale = DB::table('jobs')->where('created_at', '<', now()->subMinutes(15)->getTimestamp())->count();
            $checks[] = self::check('Kuyrukta bekleyen iş', $stale > 0 ? self::WARN : self::OK, (string) $pending,
                $stale > 0 ? 'İşler 15 dakikadan uzun süredir bekliyor; kuyruk işçisi (php artisan queue:work) çalışmıyor olabilir.' : null);
        }

        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->count();
            $checks[] = self::check('Başarısız iş', $failed > 0 ? self::WARN : self::OK, (string) $failed);
        }

        return $checks;
    }

    /**
     * @return list<array{label: string, status: string, value: string, hint: string|null}>
     */
    private function mailAndBackups(): array
    {
        $mailer = (string) config('mail.default');
        $last = $this->backups->lastResult();
        $lastAt = $last ? Carbon::parse($last['at']) : null;

        $backupStatus = match (true) {
            $last === null => self::FAIL,
            ! $last['ok'] => self::FAIL,
            $lastAt !== null && $lastAt->lt(now()->subHours((int) config('backup.max_age_hours'))) => self::WARN,
            default => self::OK,
        };

        return [
            self::check('E-posta gönderimi', in_array($mailer, ['log', 'array'], true) ? self::WARN : self::OK, $mailer,
                in_array($mailer, ['log', 'array'], true) ? 'E-postalar gönderilmiyor, yalnızca günlüğe yazılıyor. SMTP bilgileri girilince açılır.' : null),
            self::check('Son yedek', $backupStatus,
                match (true) {
                    $last === null => 'Hiç alınmadı',
                    ! $last['ok'] => 'Başarısız ('.$lastAt?->format('d.m.Y H:i').')',
                    default => $lastAt?->format('d.m.Y H:i').' · '.self::bytes((int) ($last['size'] ?? 0)),
                },
                $last !== null && ! $last['ok'] ? ($last['error'] ?? null) : ($backupStatus === self::OK ? null : 'Yedekler sekmesinden şimdi yedek alın; günlük otomatik yedek zamanlayıcıya bağlıdır.')),
        ];
    }

    private function databaseVersion(): string
    {
        try {
            return match (DB::connection()->getDriverName()) {
                'sqlite' => (string) DB::selectOne('select sqlite_version() as v')?->v,
                default => (string) DB::selectOne('select version() as v')?->v,
            };
        } catch (Throwable) {
            return '';
        }
    }

    private function pendingMigrations(): int
    {
        try {
            /** @var Migrator $migrator */
            $migrator = app('migrator');

            if (! $migrator->repositoryExists()) {
                return 0;
            }

            $files = $migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]);

            return count(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array{label: string, status: string, value: string, hint: string|null}
     */
    private static function check(string $label, string $status, string $value, ?string $hint = null): array
    {
        return ['label' => $label, 'status' => $status, 'value' => $value, 'hint' => $status === self::OK ? null : $hint];
    }

    public static function bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

        return number_format($bytes / 1024 ** $power, $power === 0 ? 0 : 1, ',', '.').' '.$units[$power];
    }
}
