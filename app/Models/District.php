<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * İlçe.
 *
 * @property int $id
 * @property int $province_id
 * @property string $name
 * @property-read Province $province
 */
#[Fillable(['province_id', 'name'])]
class District extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
