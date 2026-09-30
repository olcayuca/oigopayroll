<?php

use App\Models\AuditLog;
use App\Support\SecuritySettings;
use Illuminate\Support\Facades\Schedule;

// Drop audit records older than the configured retention (Admin → Güvenlik → Ayarlar).
Schedule::call(function () {
    AuditLog::where('created_at', '<', now()->subDays(SecuritySettings::auditRetentionDays()))->delete();
})->daily()->description('Eski olay kayıtlarını temizle');
