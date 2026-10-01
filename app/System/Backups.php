<?php

namespace App\System;

use App\Enums\AuditEvent;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Compressed database backups in storage/app/private/backups.
 *
 * MySQL is dumped with mysqldump (password passed through the environment, never on the
 * command line); SQLite files are copied. Backups contain personal data and encrypted
 * credentials, so they are kept off the public disk and are not downloadable from the UI.
 */
class Backups
{
    public const LAST_RESULT = 'system.last_backup';

    public function directory(): string
    {
        $directory = storage_path('app/private/'.config('backup.path'));

        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }

        return $directory;
    }

    /**
     * Take a backup now and prune old ones.
     *
     * @param  string|null  $connection  database connection (default: the application's)
     * @return array{file: string, size: int}
     */
    public function create(?string $connection = null): array
    {
        $raw = $this->directory().DIRECTORY_SEPARATOR.'yedek-'.now()->format('Y-m-d-His').'.sql';

        try {
            $this->dump($raw, $connection);
            $file = $this->compress($raw);
            $size = (int) filesize($file);
            $this->prune();
        } catch (Throwable $e) {
            @unlink($raw);
            Cache::forever(self::LAST_RESULT, ['ok' => false, 'at' => now()->toIso8601String(), 'error' => mb_substr($e->getMessage(), 0, 500)]);
            Audit::log(AuditEvent::BackupFailed, 'Veritabanı yedeği alınamadı: '.mb_substr($e->getMessage(), 0, 150));

            throw $e;
        }

        Cache::forever(self::LAST_RESULT, ['ok' => true, 'at' => now()->toIso8601String(), 'file' => basename($file), 'size' => $size]);
        Audit::log(AuditEvent::BackupCreated, 'Veritabanı yedeği alındı: '.basename($file), null, ['size' => $size]);

        return ['file' => basename($file), 'size' => $size];
    }

    /**
     * Existing backups, newest first.
     *
     * @return Collection<int, array{name: string, size: int, created_at: Carbon}>
     */
    public function all(): Collection
    {
        return collect(glob($this->directory().DIRECTORY_SEPARATOR.'yedek-*.gz') ?: [])
            ->map(fn (string $path) => [
                'name' => basename($path),
                'size' => (int) filesize($path),
                'created_at' => Carbon::createFromTimestamp((int) filemtime($path)),
            ])
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * @return array{ok: bool, at: string, file?: string, size?: int, error?: string}|null
     */
    public function lastResult(): ?array
    {
        $stored = Cache::get(self::LAST_RESULT);

        if (! is_array($stored) || ! is_bool($stored['ok'] ?? null) || ! is_string($stored['at'] ?? null)) {
            return null;
        }

        $result = ['ok' => $stored['ok'], 'at' => $stored['at']];

        if (is_string($stored['file'] ?? null)) {
            $result['file'] = $stored['file'];
        }

        if (is_int($stored['size'] ?? null)) {
            $result['size'] = $stored['size'];
        }

        if (is_string($stored['error'] ?? null)) {
            $result['error'] = $stored['error'];
        }

        return $result;
    }

    private function dump(string $target, ?string $name): void
    {
        $connection = DB::connection($name);
        $config = $connection->getConfig();

        match ($connection->getDriverName()) {
            'mysql', 'mariadb' => $this->mysqldump($config, $target),
            'sqlite' => $this->copySqlite((string) ($config['database'] ?? ''), $target),
            default => throw new RuntimeException('Bu veritabanı türü için yedekleme desteklenmiyor.'),
        };

        if (! is_file($target) || filesize($target) === 0) {
            throw new RuntimeException('Yedek dosyası oluşturulamadı.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function mysqldump(array $config, string $target): void
    {
        $result = Process::env(['MYSQL_PWD' => (string) ($config['password'] ?? '')])
            ->timeout(1800)
            ->run([
                (string) config('backup.mysqldump'),
                '--host='.$config['host'],
                '--port='.$config['port'],
                '--user='.$config['username'],
                '--single-transaction',
                '--quick',
                '--routines',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                '--result-file='.$target,
                (string) $config['database'],
            ]);

        if ($result->failed()) {
            throw new RuntimeException('mysqldump çalıştırılamadı: '.trim($result->errorOutput() ?: $result->output()));
        }
    }

    private function copySqlite(string $database, string $target): void
    {
        if ($database === '' || $database === ':memory:' || ! is_file($database)) {
            throw new RuntimeException('SQLite veritabanı dosyası bulunamadı.');
        }

        copy($database, $target);
    }

    private function compress(string $raw): string
    {
        $target = $raw.'.gz';
        $in = fopen($raw, 'rb');
        $out = gzopen($target, 'wb6');

        if ($in === false || $out === false) {
            throw new RuntimeException('Yedek sıkıştırılamadı.');
        }

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1024 * 1024));
        }

        fclose($in);
        gzclose($out);
        unlink($raw);

        return $target;
    }

    private function prune(): void
    {
        $this->all()->slice(max(1, (int) config('backup.keep')))
            ->each(fn (array $backup) => @unlink($this->directory().DIRECTORY_SEPARATOR.$backup['name']));
    }
}
