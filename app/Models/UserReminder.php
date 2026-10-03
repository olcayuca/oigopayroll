<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's personal reminder (assistant shortcut): announced once in the notification bell at remind_at.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $firm_id
 * @property string $title
 * @property string|null $note
 * @property Carbon $remind_at
 * @property Carbon|null $notified_at
 * @property-read User $user
 * @property-read Firm|null $firm
 */
#[Fillable(['user_id', 'firm_id', 'title', 'note', 'remind_at'])]
class UserReminder extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Firm, $this>
     */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    /**
     * Reminders whose time has come and that were not announced yet.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->whereNull('notified_at')->where('remind_at', '<=', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'note' => 'encrypted',
            'remind_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }
}
