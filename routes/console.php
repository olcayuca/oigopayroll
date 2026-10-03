<?php

use App\Actions\Firms\ManageContracts;
use App\Billing\SubscriptionBilling;
use App\Models\AuditLog;
use App\Notifications\DeliverAnnouncements;
use App\Notifications\DeliverDueReminders;
use App\Notifications\SendReminders;
use App\Support\SecuritySettings;
use App\System\HealthChecks;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Proof that the scheduler runs (Admin → Sistem Sağlığı).
Schedule::call(fn () => Cache::forever(HealthChecks::SCHEDULER_HEARTBEAT, now()->getTimestamp()))
    ->everyMinute()->description('Zamanlayıcı nabzı');

Schedule::call(fn () => app(ManageContracts::class)->renewExpired())
    ->dailyAt('00:15')->description('Otomatik yenilenen sözleşmeleri uzat');

Schedule::call(fn () => app(SendReminders::class)->run())
    ->dailyAt('08:00')->description('Belge ve sözleşme hatırlatmaları');

Schedule::call(fn () => app(DeliverDueReminders::class)->run())
    ->everyMinute()->description('Kişisel hatırlatıcılar');

Schedule::call(fn () => app(DeliverAnnouncements::class)->run())
    ->everyMinute()->description('Yayına giren duyuruların bildirimi');

// Abonelik: issue the current period's invoices, then charge due ones from saved cards.
Schedule::call(function () {
    $billing = app(SubscriptionBilling::class);
    $billing->issueInvoices();
    $billing->chargeDue();
})->dailyAt('07:00')->description('Abonelik faturaları ve otomatik kart çekimi');

Schedule::command('hrd:yedek')->dailyAt('02:30')->withoutOverlapping()->description('Günlük veritabanı yedeği');

// Drop audit records older than the configured retention (Admin → Güvenlik → Ayarlar).
Schedule::call(function () {
    AuditLog::where('created_at', '<', now()->subDays(SecuritySettings::auditRetentionDays()))->delete();
})->daily()->description('Eski olay kayıtlarını temizle');
