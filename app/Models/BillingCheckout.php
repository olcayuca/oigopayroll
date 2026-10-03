<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One visit to iyzico's hosted payment page (pay an invoice, or save a card).
 *
 * @property int $id
 * @property int $firm_id
 * @property int|null $invoice_id
 * @property int|null $user_id
 * @property string $purpose invoice | card
 * @property string $conversation_id
 * @property string|null $token
 * @property string $status started | completed | failed
 * @property string|null $message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Firm $firm
 * @property-read Invoice|null $invoice
 * @property-read User|null $user
 */
#[Fillable(['firm_id', 'invoice_id', 'user_id', 'purpose', 'conversation_id', 'token', 'status', 'message'])]
class BillingCheckout extends Model
{
    /**
     * @return BelongsTo<Firm, $this>
     */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
