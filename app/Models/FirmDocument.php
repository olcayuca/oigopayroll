<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A file kept for a firm (or one of its companies). Stored on a private disk; downloads go
 * through DownloadFirmDocument, which checks access and writes an audit record.
 *
 * @property int $id
 * @property int $firm_id
 * @property int|null $company_id
 * @property DocumentType $type
 * @property string $title
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $valid_until
 * @property string|null $notes
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Firm $firm
 * @property-read Company|null $company
 * @property-read User|null $uploader
 */
#[Fillable(['firm_id', 'company_id', 'type', 'title', 'disk', 'path', 'original_name', 'mime_type', 'size', 'valid_until', 'notes', 'uploaded_by'])]
class FirmDocument extends Model
{
    /** Days before expiry when a document is flagged. */
    public const EXPIRY_WARNING_DAYS = 30;

    public const EXPIRED = 'expired';

    public const EXPIRING = 'expiring';

    public const VALID = 'valid';

    /**
     * @return BelongsTo<Firm, $this>
     */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Expired, expiring within EXPIRY_WARNING_DAYS, or valid (also when there is no expiry date).
     */
    public function validity(): string
    {
        return match (true) {
            $this->valid_until === null => self::VALID,
            $this->valid_until->lt(today()) => self::EXPIRED,
            $this->valid_until->lte(today()->addDays(self::EXPIRY_WARNING_DAYS)) => self::EXPIRING,
            default => self::VALID,
        };
    }

    /**
     * Documents that are expired or expire within the warning period.
     *
     * @param  Builder<FirmDocument>  $query
     */
    public function scopeNeedsAttention(Builder $query): void
    {
        $query->whereNotNull('valid_until')->whereDate('valid_until', '<=', today()->addDays(self::EXPIRY_WARNING_DAYS));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'valid_until' => 'date',
            'size' => 'integer',
        ];
    }
}
