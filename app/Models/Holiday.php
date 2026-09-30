<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Official holiday (resmi tatil). Half days (arife, 28 Ekim) start at 13:00.
 *
 * @property int $id
 * @property Carbon $date
 * @property string $name
 * @property string $type national | religious
 * @property bool $is_half_day
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['date', 'name', 'type', 'is_half_day'])]
class Holiday extends Model
{
    public const TYPES = ['national' => 'Ulusal', 'religious' => 'Dini'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_half_day' => 'boolean',
        ];
    }
}
