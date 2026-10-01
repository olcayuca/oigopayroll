<?php

namespace App\Actions\Firms;

use App\Enums\AuditEvent;
use App\Enums\FirmStatus;
use App\Models\Firm;
use App\Models\User;
use App\Notifications\FirmReviewed;
use App\Notifications\Recipients;
use App\Support\Audit;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * HRD approves or rejects a firm that is waiting for review.
 */
class ReviewFirm
{
    public function approve(Firm $firm, User $reviewer): Firm
    {
        $this->ensurePending($firm);

        $firm->update([
            'status' => FirmStatus::Active,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        Audit::log(AuditEvent::FirmApproved, "Firma onaylandı: {$firm->name}", $firm);
        Notification::send(Recipients::firm($firm), new FirmReviewed($firm, approved: true));

        return $firm;
    }

    public function reject(Firm $firm, User $reviewer, string $reason): Firm
    {
        $this->ensurePending($firm);

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['rejection_reason' => 'Red gerekçesi zorunludur.']);
        }

        $firm->update([
            'status' => FirmStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        Audit::log(AuditEvent::FirmRejected, "Firma reddedildi: {$firm->name}", $firm, ['reason' => $reason]);
        Notification::send(Recipients::firm($firm), new FirmReviewed($firm, approved: false));

        return $firm;
    }

    private function ensurePending(Firm $firm): void
    {
        if ($firm->status !== FirmStatus::Pending) {
            throw ValidationException::withMessages(['firm' => 'Yalnızca onay bekleyen firmalar incelenebilir.']);
        }
    }
}
