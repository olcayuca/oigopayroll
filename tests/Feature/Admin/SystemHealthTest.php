<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditEvent;
use App\Enums\Portal;
use App\Models\AuditLog;
use App\Models\User;
use App\System\Backups;
use App\System\ErrorLog;
use App\System\HealthChecks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        config(['backup.path' => 'backups-test-'.uniqid()]);
        $this->backupDir = storage_path('app/private/'.config('backup.path'));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->backupDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->backupDir);

        parent::tearDown();
    }

    /**
     * Pretend to be MySQL and fake mysqldump writing the dump file.
     */
    private function fakeMysqldump(bool $succeeds = true): void
    {
        config(['database.connections.testing_mysql' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3306', 'database' => 'oigo', 'username' => 'u', 'password' => 'p@ss',
        ]]);

        Process::fake(function (PendingProcess $process) use ($succeeds) {
            $command = (array) $process->command;
            $resultFile = collect($command)->first(fn ($part) => str_starts_with((string) $part, '--result-file='));

            if ($succeeds && $resultFile) {
                file_put_contents(substr((string) $resultFile, strlen('--result-file=')), "-- dump\nCREATE TABLE x (id int);\n");
            }

            return $succeeds ? Process::result('') : Process::result('', 'Access denied', 1);
        });
    }

    public function test_backup_with_mysqldump_compresses_prunes_and_hides_the_password(): void
    {
        $this->fakeMysqldump();
        config(['backup.keep' => 2]);
        $backups = app(Backups::class);

        foreach (range(1, 3) as $i) {
            $this->travel(1)->seconds();
            $backups->create('testing_mysql');
            touch($this->backupDir.'/'.$backups->all()->first()['name'], now()->getTimestamp());
        }

        $this->assertCount(2, $backups->all(), 'Only the newest backups are kept.');
        $this->assertStringContainsString('CREATE TABLE', (string) gzdecode((string) file_get_contents($this->backupDir.'/'.$backups->all()->first()['name'])));
        $this->assertTrue($backups->lastResult()['ok'] ?? false);

        Process::assertRan(function (PendingProcess $process) {
            $command = implode(' ', (array) $process->command);

            return str_contains($command, '--single-transaction') && ! str_contains($command, 'p@ss')
                && ($process->environment['MYSQL_PWD'] ?? null) === 'p@ss';
        });
    }

    public function test_failed_backup_is_recorded(): void
    {
        $this->fakeMysqldump(succeeds: false);

        try {
            app(Backups::class)->create('testing_mysql');
            $this->fail('Expected failure.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Access denied', $e->getMessage());
        }

        $this->assertFalse(app(Backups::class)->lastResult()['ok'] ?? true);
        $this->assertTrue(AuditLog::where('event', AuditEvent::BackupFailed)->exists());
        $this->assertSame([], glob($this->backupDir.'/*') ?: []);
    }

    public function test_health_checks_flag_missing_scheduler_and_backup(): void
    {
        $results = app(HealthChecks::class)->run();
        $checks = collect($results)->flatten(1)->keyBy('label');

        $this->assertSame(HealthChecks::FAIL, $checks['Zamanlayıcı']['status']);
        $this->assertSame(HealthChecks::FAIL, $checks['Son yedek']['status']);
        $this->assertSame(HealthChecks::OK, $checks['PHP sürümü']['status']);
        $this->assertSame(HealthChecks::FAIL, HealthChecks::overall($results));

        Cache::forever(HealthChecks::SCHEDULER_HEARTBEAT, now()->getTimestamp());
        Cache::forever(Backups::LAST_RESULT, ['ok' => true, 'at' => now()->toIso8601String(), 'file' => 'x.gz', 'size' => 10]);
        $checks = collect(app(HealthChecks::class)->run())->flatten(1)->keyBy('label');

        $this->assertSame(HealthChecks::OK, $checks['Zamanlayıcı']['status']);
        $this->assertSame(HealthChecks::OK, $checks['Son yedek']['status']);
    }

    public function test_error_log_reader_returns_recent_errors_only(): void
    {
        $log = storage_path('logs/health-test.log');
        file_put_contents($log, implode("\n", [
            '[2026-09-30 10:00:00] local.INFO: bilgi',
            '[2026-09-30 11:00:00] local.ERROR: Bir şey patladı {"exception":"..."}',
            '#0 stack trace line',
            '[2026-09-30 12:00:00] production.CRITICAL: Kritik hata',
        ]));
        touch($log, time() + 60);

        try {
            $errors = app(ErrorLog::class)->recent();
        } finally {
            @unlink($log);
        }

        $messages = array_column($errors, 'message');
        $this->assertContains('Kritik hata', $messages);
        $this->assertNotContains('bilgi', $messages);
        $this->assertSame('CRITICAL', collect($errors)->firstWhere('message', 'Kritik hata')['level'] ?? null);
    }

    public function test_admin_page_tabs_and_backup_button(): void
    {
        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());

        foreach (['durum', 'yedekler', 'hatalar'] as $tab) {
            $this->get(route('admin.system.health', ['sekme' => $tab]))->assertOk()->assertSee('role="tablist"', false);
        }
        $this->get(route('admin.system.health'))->assertSee('Zamanlayıcı')->assertSee('PHP sürümü');

        // SQLite in-memory cannot be backed up: the page reports it instead of crashing.
        Livewire::test('pages::admin.system.health')->call('backupNow')->assertOk();
        $this->assertFalse(app(Backups::class)->lastResult()['ok'] ?? true);
    }
}
