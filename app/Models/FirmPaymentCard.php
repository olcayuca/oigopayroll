<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A firm's card saved at iyzico. Only the provider's tokens (encrypted) and display data are kept.
 *
 * @property int $id
 * @property int $firm_id
 * @property string $provider
 * @property string $card_user_key
 * @property string $card_token
 * @property string|null $last_four
 * @property string|null $bin_number
 * @property string|null $card_association
 * @property string|null $card_family
 * @property bool $is_default
 * @property int|null $added_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Firm $firm
 * @property-read User|null $adder
 */
#[Fillable(['firm_id', 'provider', 'card_user_key', 'card_token', 'last_four', 'bin_number', 'card_association', 'card_family', 'is_default', 'added_by'])]
#[Hidden(['card_user_key', 'card_token'])]
class FirmPaymentCard extends Model
{
    /**
     * @return BelongsTo<Firm, $this>
     */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function adder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * "VISA •••• 4242 · Bonus"
     */
    public function label(): string
    {
        $brand = match ($this->card_association) {
            'MASTER_CARD' => 'Mastercard',
            'VISA' => 'Visa',
            'TROY' => 'Troy',
            'AMERICAN_EXPRESS' => 'American Express',
            default => 'Kart',
        };

        return trim($brand.' •••• '.($this->last_four ?? '••••').($this->card_family ? ' · '.$this->card_family : ''));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'card_user_key' => 'encrypted',
            'card_token' => 'encrypted',
            'is_default' => 'boolean',
        ];
    }
}
