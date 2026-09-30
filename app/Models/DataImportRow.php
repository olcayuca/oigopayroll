<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One spreadsheet row of an import, with its normalised data and validation errors.
 *
 * @property int $id
 * @property int $data_import_id
 * @property int $row_number
 * @property array<string, mixed> $data
 * @property array<string, list<string>>|null $errors
 * @property string|null $created_record_type
 * @property int|null $created_record_id
 * @property-read DataImport $import
 * @property-read Model|null $createdRecord
 */
#[Fillable(['data_import_id', 'row_number', 'data', 'errors', 'created_record_type', 'created_record_id'])]
class DataImportRow extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<DataImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(DataImport::class, 'data_import_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function createdRecord(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Determine if the row has validation errors.
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'encrypted:array',
            'errors' => 'array',
        ];
    }
}
