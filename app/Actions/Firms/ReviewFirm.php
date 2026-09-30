<?php

namespace App\Actions\Firms;

use App\Enums\FirmStatus;
use App\Models\Firm;
use App\Models\User;
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

        return $firm;
    }

    private function ensurePending(Firm $firm): void
    {
        if ($firm->status !== FirmStatus::Pending) {
            throw ValidationException::withMessages(['firm' => 'Yalnızca onay bekleyen firmalar incelenebilir.']);
        }
    }
}
