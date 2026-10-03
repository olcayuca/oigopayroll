<?php

namespace App\Models;

use App\Enums\DefinitionType;
use App\Support\Text;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A firm-level list entry (Tanımlar): birim, unvan, pozisyon, masraf grubu…
 *
 * @property int $id
 * @property int $firm_id
 * @property DefinitionType $type
 * @property string $code
 * @property string $name
 * @property int|null $parent_id
 * @property array<string, string|null>|null $extra
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['firm_id', 'type', 'code', 'name', 'parent_id', 'extra', 'is_active'])]
class Definition extends Model
{
    /**
     * @return BelongsTo<Firm, $this>
     */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    /**
     * @return BelongsTo<Definition, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Definition::class, 'parent_id');
    }

    /**
     * A free code derived from the name ("İnsan Kaynakları" → "INSANKAY", "INSANKAY2", …).
     */
    public static function uniqueCode(Firm $firm, DefinitionType $type, string $name): string
    {
        $base = mb_strtoupper(mb_substr(Text::key($name), 0, 8)) ?: 'TANIM';
        $code = $base;

        for ($i = 2; self::query()->ofType($firm, $type)->where('code', $code)->exists(); $i++) {
            $code = $base.$i;
        }

        return $code;
    }

    /**
     * Number of personnel records using this entry.
     */
    public function usageCount(): int
    {
        return Employee::query()->where($this->type->employeeColumn(), $this->id)->count();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfType(Builder $query, Firm|int $firm, DefinitionType $type): void
    {
        $query->where('firm_id', $firm instanceof Firm ? $firm->id : $firm)->where('type', $type);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DefinitionType::class,
            'extra' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
