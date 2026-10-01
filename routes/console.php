<?php

use App\Actions\Firms\ManageContracts;
use App\Models\AuditLog;
use App\Support\SecuritySettings;
use App\System\HealthChecks;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Proof that the scheduler runs (Admin → Sistem Sağlığı).
Schedule::call(fn () => Cache::forever(HealthChecks::SCHEDULER_HEARTBEAT, now()->getTimestamp()))
    ->everyMinute()->description('Zamanlayıcı nabzı');

Schedule::call(fn () => app(ManageContracts::class)->renewExpired())
    ->dailyAt('00:15')->description('Otomatik yenilenen sözleşmeleri uzat');

Schedule::command('hrd:yedek')->dailyAt('02:30')->withoutOverlapping()->description('Günlük veritabanı yedeği');

// Drop audit records older than the configured retention (Admin → Güvenlik → Ayarlar).
Schedule::call(function () {
    AuditLog::where('created_at', '<', now()->subDays(SecuritySettings::auditRetentionDays()))->delete();
})->daily()->description('Eski olay kayıtlarını temizle');
