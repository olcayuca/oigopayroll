<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Destek talebi of a firm, with its message thread (SupportMessage).
 *
 * @property int $id
 * @property int $firm_id
 * @property int|null $user_id
 * @property int|null $assigned_to
 * @property string $subject
 * @property string $category
 * @property string $module
 * @property TicketPriority $priority
 * @property TicketStatus $status
 * @property Carbon|null $last_message_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Firm $firm
 * @property-read User|null $user
 * @property-read User|null $assignee
 * @property-read Collection<int, SupportMessage> $messages
 */
#[Fillable(['firm_id', 'user_id', 'assigned_to', 'subject', 'category', 'module', 'priority', 'status', 'last_message_at', 'closed_at'])]
class SupportTicket extends Model
{
    public const CATEGORIES = ['Kurulum', 'Hesaplama sorusu', 'Hata bildirimi', 'Mevzuat sorusu', 'Kullanıcı / yetki', 'Lisans & fatura', 'Öneri'];

    public const MODULES = ['Şirketler & İşyerleri', 'Personel', 'Tanımlar', 'Excel aktarımı', 'Bordro Dönemleri', 'Hesaplamalar', 'Raporlar', 'Diğer'];

    /** Talep numarası: #1001, #1002, … */
    public function number(): string
    {
        return '#'.(1000 + $this->id);
    }

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
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<SupportMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('id');
    }

    /**
     * "Açık talepler" (still being worked on) or "Geçmiş".
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query, bool $active = true): void
    {
        $active
            ? $query->whereIn('status', TicketStatus::active())
            : $query->whereNotIn('status', TicketStatus::active());
    }

    /**
     * Tickets of the firm the user may follow in the panel: their own, all of the firm for
     * firm-level managers and HRD staff.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleInPanel(Builder $query, User $user, Firm $firm): void
    {
        $query->where('firm_id', $firm->id)
            ->when(! $user->can('viewAllSupportTickets', $firm), fn (Builder $query) => $query->where('user_id', $user->id));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
