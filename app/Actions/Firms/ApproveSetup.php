<?php

namespace App\Actions\Firms;

use App\Enums\AuditEvent;
use App\Models\Firm;
use App\Models\User;
use App\Notifications\Recipients;
use App\Notifications\SetupApproved;
use App\Support\Audit;
use App\Support\SetupStatus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Kurulum onayı: the payroll specialist confirms a complete setup (all wizard steps done).
 * Authorization is the caller's job (FirmPolicy::approveSetup).
 */
class ApproveSetup
{
    public function approve(Firm $firm, User $user, ?SetupStatus $status = null): void
    {
        $status ??= new SetupStatus($user, $firm);

        if (! $status->complete()) {
            throw ValidationException::withMessages(['setup' => 'Kurulum adımları tamamlanmadan onay verilemez.']);
        }

        $firm->forceFill(['setup_approved_at' => now(), 'setup_approved_by' => $user->id])->save();
        Audit::log(AuditEvent::FirmSetupApproved, "Kurulum onaylandı: {$firm->name}", $firm, user: $user);

        Notification::send(Recipients::firm($firm)->reject(fn (User $recipient) => $recipient->is($user)), new SetupApproved($firm, $user));
    }

    public function revoke(Firm $firm): void
    {
        $firm->forceFill(['setup_approved_at' => null, 'setup_approved_by' => null])->save();
        Audit::log(AuditEvent::FirmSetupApprovalRevoked, "Kurulum onayı kaldırıldı: {$firm->name}", $firm);
    }
}
