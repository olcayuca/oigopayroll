<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's personal note (assistant shortcut), kept per firm.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $firm_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'firm_id', 'body'])]
class UserNote extends Model
{
    public const MAX_LENGTH = 2000;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Notes of the user in the given firm (or outside any firm).
     *
     * @param  Builder<self>  $query
     */
    public function scopeOwnedBy(Builder $query, User $user, ?Firm $firm): void
    {
        $query->where('user_id', $user->id)->where('firm_id', $firm?->id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }
}
