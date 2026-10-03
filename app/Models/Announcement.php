<?php

namespace App\Models;

use App\Enums\Portal;
use App\Enums\UserType;
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
 * @property string $category Mevzuat | Sistem | Bakım | Yeni Özellik
 * @property bool $pinned shown first on the Duyurular page
 * @property string|null $link_label
 * @property string|null $link_url panel path ("/personel") or https:// address
 * @property bool $notify send a bell / e-mail notification when it goes live
 * @property Carbon|null $notified_at
 * @property string $audience all | clients | staff | firms
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Collection<int, Firm> $firms target firms when audience = firms
 */
#[Fillable(['title', 'body', 'level', 'category', 'pinned', 'link_label', 'link_url', 'audience', 'starts_at', 'ends_at', 'notify', 'notified_at', 'created_by'])]
class Announcement extends Model
{
    public const LEVELS = ['info' => 'Bilgi', 'warning' => 'Uyarı', 'critical' => 'Kritik'];

    public const AUDIENCES = ['all' => 'Tüm panel kullanıcıları', 'clients' => 'Yalnızca müşteri kullanıcıları', 'staff' => 'Yalnızca HRD personeli', 'firms' => 'Seçili firmalar'];

    /** Category => badge color (prototype ANN_CAT). */
    public const CATEGORIES = ['Mevzuat' => 'amber', 'Sistem' => 'blue', 'Bakım' => 'red', 'Yeni Özellik' => 'green'];

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
     * @return BelongsToMany<User, $this>
     */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_reads')->withPivot('read_at');
    }

    public function markReadBy(User $user): void
    {
        $this->readers()->syncWithoutDetaching([$user->id => ['read_at' => now()]]);
    }

    /**
     * Link target: panel paths stay on the panel, other addresses open as given (https only, validated on save).
     */
    public function linkHref(): ?string
    {
        if (blank($this->link_url) || blank($this->link_label)) {
            return null;
        }

        return str_starts_with((string) $this->link_url, '/') ? Portal::Panel->url((string) $this->link_url) : $this->link_url;
    }

    /**
     * Active users the announcement is meant for (notification on publish).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function recipients(): \Illuminate\Support\Collection
    {
        $users = User::query()->where('is_active', true)
            ->when($this->audience === 'clients', fn ($query) => $query->where('type', UserType::ClientUser))
            ->when($this->audience === 'staff', fn ($query) => $query->where('type', '!=', UserType::ClientUser))
            ->get();

        if ($this->audience !== 'firms') {
            return $users;
        }

        $firmIds = $this->firms()->pluck('firms.id')->all();

        return $users->filter(fn (User $user) => collect($firmIds)->contains(fn (int $id) => $user->mayWorkInFirm($id)))->values();
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
     * Announcements meant for the user (any time; combine with live() or the archive).
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeForAudience(Builder $query, User $user): void
    {
        $firmIds = array_values(array_unique(array_filter([$user->firm_id, $user->current_firm_id])));

        $query->where(function (Builder $inner) use ($user, $firmIds) {
            $inner->where('audience', 'all')
                ->orWhere('audience', $user->type->isClient() ? 'clients' : 'staff')
                ->orWhere(fn (Builder $firms) => $firms->where('audience', 'firms')
                    ->whereHas('firms', fn ($targets) => $targets->whereIn('firms.id', $firmIds)));
        });
    }

    /**
     * Not read yet by the user.
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeUnreadBy(Builder $query, User $user): void
    {
        $query->whereDoesntHave('readers', fn ($readers) => $readers->whereKey($user->id));
    }

    /**
     * Live announcements meant for the user that they have not dismissed (top strip).
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeFor(Builder $query, User $user): void
    {
        $query->live()->forAudience($user)
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
            'pinned' => 'boolean',
            'notify' => 'boolean',
            'notified_at' => 'datetime',
        ];
    }
}
