<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One message of a support ticket; an optional attachment is kept on the private disk.
 *
 * @property int $id
 * @property int $support_ticket_id
 * @property int|null $user_id
 * @property bool $from_staff
 * @property string $body
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property string|null $attachment_mime
 * @property int|null $attachment_size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupportTicket $ticket
 * @property-read User|null $user
 */
#[Fillable(['support_ticket_id', 'user_id', 'from_staff', 'body', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size'])]
class SupportMessage extends Model
{
    public const DISK = 'local';

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Name shown in the thread: HRD staff are shown with the company name.
     */
    public function authorName(): string
    {
        $name = $this->user->name ?? 'Silinmiş kullanıcı';

        return $this->from_staff ? $name.' · HRD Destek' : $name;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['from_staff' => 'boolean'];
    }
}
