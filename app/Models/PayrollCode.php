<?php

namespace App\Models;

use App\Enums\CodeList;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An entry of an official payroll code list.
 *
 * @property int $id
 * @property CodeList $list
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['list', 'code', 'name', 'description', 'is_active'])]
class PayrollCode extends Model
{
    /**
     * Active entries of a list, ordered by code (for dropdowns).
     *
     * @param  Builder<self>  $query
     */
    public function scopeOptions(Builder $query, CodeList $list): void
    {
        $query->where('list', $list)->where('is_active', true)->orderBy('code');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'list' => CodeList::class,
            'is_active' => 'boolean',
        ];
    }
}
