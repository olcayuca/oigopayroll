<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Panel announcement (duyuru).
 *
 * @property int $id
 * @property string $title
 * @property string $body Markdown
 * @property string $level info | warning | critical
 * @property string $audience all | clients | staff | firms
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Collection<int, Firm> $firms target firms when audience = firms
 */
#[Fillable(['title', 'body', 'level', 'audience', 'starts_at', 'ends_at', 'created_by'])]
class Announcement extends Model
{
    public const LEVELS = ['info' => 'Bilgi', 'warning' => 'Uyarı', 'critical' => 'Kritik'];

    public const AUDIENCES = ['all' => 'Tüm panel kullanıcıları', 'clients' => 'Yalnızca müşteri kullanıcıları', 'staff' => 'Yalnızca HRD personeli', 'firms' => 'Seçili firmalar'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<Firm, $this>
     */
    public function firms(): BelongsToMany
    {
        return $this->belongsToMany(Firm::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function dismissedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_dismissals')->withPivot('dismissed_at');
    }

    /**
     * Critical announcements stay until they end.
     */
    public function isDismissible(): bool
    {
        return $this->level !== 'critical';
    }

    public function isLive(): bool
    {
        return $this->starts_at->lte(now()) && ($this->ends_at === null || $this->ends_at->gt(now()));
    }

    public function html(): HtmlString
    {
        return new HtmlString(Str::markdown($this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }

    /**
     * @param  Builder<Announcement>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('starts_at', '<=', now())
            ->where(fn ($inner) => $inner->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /**
     * Live announcements meant for the user that they have not dismissed.
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeFor(Builder $query, User $user): void
    {
        $firmIds = array_values(array_unique(array_filter([$user->firm_id, $user->current_firm_id])));

        $query->live()
            ->where(function (Builder $inner) use ($user, $firmIds) {
                $inner->where('audience', 'all')
                    ->orWhere('audience', $user->type->isClient() ? 'clients' : 'staff')
                    ->orWhere(fn (Builder $firms) => $firms->where('audience', 'firms')
                        ->whereHas('firms', fn ($targets) => $targets->whereIn('firms.id', $firmIds)));
            })
            ->whereDoesntHave('dismissedBy', fn ($dismissed) => $dismissed->whereKey($user->id))
            ->orderByRaw("case level when 'critical' then 0 when 'warning' then 1 else 2 end")
            ->latest('starts_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
