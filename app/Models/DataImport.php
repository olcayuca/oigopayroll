<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An uploaded Excel file waiting for preview confirmation (or already applied).
 *
 * @property int $id
 * @property ImportType $type
 * @property ImportStatus $status
 * @property int|null $firm_id
 * @property int|null $user_id
 * @property string $original_filename
 * @property list<string>|null $file_errors
 * @property int $total_rows
 * @property int $error_rows
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Firm|null $firm
 * @property-read User|null $user
 * @property-read Collection<int, DataImportRow> $rows
 */
#[Fillable(['type', 'status', 'firm_id', 'user_id', 'original_filename', 'file_errors', 'total_rows', 'error_rows', 'completed_at'])]
class DataImport extends Model
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<DataImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(DataImportRow::class)->orderBy('row_number');
    }

    /**
     * Determine if the preview is clean and may be confirmed.
     */
    public function canBeConfirmed(): bool
    {
        return $this->status === ImportStatus::Validated
            && empty($this->file_errors)
            && $this->error_rows === 0
            && $this->total_rows > 0;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ImportType::class,
            'status' => ImportStatus::class,
            'file_errors' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
