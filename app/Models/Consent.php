<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's decision on a policy document version.
 *
 * @property int $id
 * @property int $user_id
 * @property int $policy_document_id
 * @property bool $accepted
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $decided_at
 * @property Carbon|null $revoked_at
 * @property-read PolicyDocument $document
 * @property-read User $user
 */
#[Fillable(['user_id', 'policy_document_id', 'accepted', 'ip_address', 'user_agent', 'decided_at', 'revoked_at'])]
class Consent extends Model
{
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted' => 'boolean',
            'decided_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PolicyDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PolicyDocument::class, 'policy_document_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the consent is currently in effect.
     */
    public function isGiven(): bool
    {
        return $this->accepted && $this->revoked_at === null;
    }
}
