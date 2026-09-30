<?php

namespace App\Models;

use App\Enums\KvkkRequestStatus;
use App\Enums\KvkkRequestType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Data subject application (KVKK md. 11). Must be answered within 30 days (md. 13).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $requester_name
 * @property string $requester_email
 * @property KvkkRequestType $type
 * @property KvkkRequestStatus $status
 * @property string $message
 * @property string|null $response
 * @property Carbon $due_at
 * @property int|null $handled_by
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read User|null $handler
 */
#[Fillable(['user_id', 'requester_name', 'requester_email', 'type', 'status', 'message', 'response', 'due_at', 'handled_by', 'resolved_at'])]
class KvkkRequest extends Model
{
    /** Legal answer period in days (KVKK md. 13/2). */
    public const ANSWER_DAYS = 30;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => KvkkRequestType::class,
            'status' => KvkkRequestStatus::class,
            'due_at' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isOverdue(): bool
    {
        return ! $this->status->isClosed() && $this->due_at->isBefore(today());
    }
}
