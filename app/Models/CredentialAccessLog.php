<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit trail of who revealed which workplace credential.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $workplace_id
 * @property string $field
 * @property string|null $ip_address
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read Workplace $workplace
 */
#[Fillable(['user_id', 'workplace_id', 'field', 'ip_address'])]
class CredentialAccessLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Workplace, $this>
     */
    public function workplace(): BelongsTo
    {
        return $this->belongsTo(Workplace::class);
    }
}
