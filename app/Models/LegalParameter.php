<?php

namespace App\Models;

use App\Payroll\Parameters\ParameterCatalog;
use App\Payroll\Parameters\ParameterDefinition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One value of a legal payroll parameter, valid from effective_from until the next entry.
 *
 * @property int $id
 * @property string $key
 * @property Carbon $effective_from
 * @property string|list<array{up_to: string|null, rate: string}> $value
 * @property string|null $source
 * @property string|null $note
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $editor
 */
#[Fillable(['key', 'effective_from', 'value', 'source', 'note', 'updated_by'])]
class LegalParameter extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function definition(): ?ParameterDefinition
    {
        return ParameterCatalog::find($this->key);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'value' => 'json',
        ];
    }
}
