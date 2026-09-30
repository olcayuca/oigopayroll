<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * İl. The id is the plate code.
 *
 * @property int $id
 * @property string $name
 * @property-read Collection<int, District> $districts
 */
#[Fillable(['id', 'name'])]
class Province extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    /**
     * @return HasMany<District, $this>
     */
    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }
}
