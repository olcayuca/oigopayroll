<?php

namespace App\Actions\Support;

use App\Enums\AuditEvent;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserType;
use App\Models\Firm;
use App\Models\Setting;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketUpdate;
use App\Policies\SupportTicketPolicy;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Destek talepleri: open, reply both ways, change status, assign. Every step notifies the other side
 * (bell + e-mail once a mailer is configured). Authorization is the caller's job (SupportTicketPolicy).
 */
class ManageSupportTickets
{
    public const MAX_BODY = 5000;

    public const ATTACHMENT_RULES = ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xlsx,xls,csv,docx,doc,txt'];

    /**
     * @param  array<string, mixed>  $input  subject, category, module, priority, body
     */
    public function open(Firm $firm, User $user, array $input, ?UploadedFile $attachment = null): SupportTicket
    {
        $data = Validator::make([...$input, 'attachment' => $attachment], [
            'subject' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(SupportTicket::CATEGORIES)],
            'module' => ['required', Rule::in(SupportTicket::MODULES)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'body' => ['required', 'string', 'max:'.self::MAX_BODY],
            'attachment' => self::ATTACHMENT_RULES,
        ], [], ['subject' => 'Konu', 'category' => 'Kategori', 'module' => 'Modül', 'priority' => 'Öncelik', 'body' => 'Açıklama', 'attachment' => 'Ek dosya'])->validate();

        $ticket = DB::transaction(function () use ($firm, $user, $data, $attachment) {
            $ticket = SupportTicket::create([
                'firm_id' => $firm->id, 'user_id' => $user->id, 'subject' => trim($data['subject']),
                'category' => $data['category'], 'module' => $data['module'], 'priority' => $data['priority'],
                'status' => TicketStatus::Open, 'last_message_at' => now(),
            ]);
            $this->addMessage($ticket, $user, $data['body'], $attachment, false);

            return $ticket;
        });

        Audit::log(AuditEvent::SupportTicketOpened, "Destek talebi açıldı {$ticket->number()}: {$ticket->subject}", $firm, ['ticket_id' => $ticket->id], $user);

        $summary = "{$firm->name} · {$ticket->category} · {$ticket->module} · öncelik {$ticket->priority->label()}\n".Str::limit(trim($data['body']), 300);
        $notification = new SupportTicketUpdate($ticket, "Yeni destek talebi {$ticket->number()}: {$ticket->subject}", $summary,
            $ticket->priority === TicketPriority::Critical ? 'danger' : 'info');

        Notification::send($this->staff($ticket, $user), $notification);

        if (filled($mailbox = Setting::get('support.email'))) {
            Notification::route('mail', (string) $mailbox)->notify($notification);
        }

        $user->notify(new SupportTicketUpdate($ticket, "Talebiniz alındı {$ticket->number()}: {$ticket->subject}",
            'HRD destek ekibine iletildi. Yanıtlandığında bildirim alacaksınız.', 'success'));

        return $ticket;
    }

    /**
     * A message on the ticket. HRD replies set "Yanıt bekleniyor" (or the chosen status); customer replies reopen it.
     */
    public function reply(SupportTicket $ticket, User $user, string $body, ?UploadedFile $attachment = null, ?TicketStatus $status = null): SupportMessage
    {
        Validator::make(['body' => $body, 'attachment' => $attachment], [
            'body' => ['required', 'string', 'max:'.self::MAX_BODY],
            'attachment' => self::ATTACHMENT_RULES,
        ], [], ['body' => 'Mesaj', 'attachment' => 'Ek dosya'])->validate();

        $staff = SupportTicketPolicy::isStaff($user, $ticket->firm);

        $message = DB::transaction(function () use ($ticket, $user, $body, $attachment, $staff, $status) {
            $message = $this->addMessage($ticket, $user, $body, $attachment, $staff);
            $next = $staff ? ($status ?? TicketStatus::AwaitingCustomer) : TicketStatus::Open;

            $ticket->update([
                'status' => $next,
                'last_message_at' => now(),
                'closed_at' => in_array($next, [TicketStatus::Resolved, TicketStatus::Closed], true) ? now() : null,
                'assigned_to' => $staff ? ($ticket->assigned_to ?? $user->id) : $ticket->assigned_to,
            ]);

            return $message;
        });

        $excerpt = Str::limit(trim($body), 300);

        if ($staff) {
            Notification::send($this->customers($ticket, $user), new SupportTicketUpdate($ticket,
                "Talebiniz yanıtlandı {$ticket->number()}: {$ticket->subject}", "{$user->name}: {$excerpt}", 'success'));
        } else {
            Notification::send($this->staff($ticket, $user), new SupportTicketUpdate($ticket,
                "Müşteri yanıtı {$ticket->number()}: {$ticket->subject}", "{$ticket->firm->name} · {$user->name}: {$excerpt}"));
        }

        return $message;
    }

    public function changeStatus(SupportTicket $ticket, User $user, TicketStatus $status): void
    {
        if ($ticket->status === $status) {
            return;
        }

        $previous = $ticket->status;
        $ticket->update([
            'status' => $status,
            'closed_at' => in_array($status, [TicketStatus::Resolved, TicketStatus::Closed], true) ? now() : null,
        ]);

        Audit::log(AuditEvent::SupportTicketStatusChanged, "Destek talebi {$ticket->number()}: {$previous->label()} → {$status->label()}", $ticket->firm,
            ['ticket_id' => $ticket->id], $user);

        $notification = new SupportTicketUpdate($ticket, "Talep durumu: {$status->label()} {$ticket->number()}", $ticket->subject,
            $status === TicketStatus::Resolved ? 'success' : 'info');

        SupportTicketPolicy::isStaff($user, $ticket->firm)
            ? Notification::send($this->customers($ticket, $user), $notification)
            : Notification::send($this->staff($ticket, $user), $notification);
    }

    public function assign(SupportTicket $ticket, ?User $assignee): void
    {
        $ticket->update(['assigned_to' => $assignee?->id]);
    }

    /**
     * HRD users who may take the ticket over (admin list): super admins and the firm's specialist.
     *
     * @return Collection<int, User>
     */
    public static function staffOptions(SupportTicket $ticket): Collection
    {
        return User::query()->where('is_active', true)
            ->where(fn ($query) => $query->where('type', UserType::SuperAdmin)->orWhere('id', $ticket->firm->specialist_id))
            ->orderBy('name')->get();
    }

    private function addMessage(SupportTicket $ticket, User $user, string $body, ?UploadedFile $attachment, bool $staff): SupportMessage
    {
        $file = [];

        if ($attachment !== null) {
            $file = [
                'attachment_path' => $attachment->store('support/'.$ticket->id, SupportMessage::DISK),
                'attachment_name' => Str::limit($attachment->getClientOriginalName(), 200, ''),
                'attachment_mime' => $attachment->getMimeType(),
                'attachment_size' => $attachment->getSize(),
            ];
        }

        return $ticket->messages()->create([...$file, 'user_id' => $user->id, 'from_staff' => $staff, 'body' => trim($body)]);
    }

    /**
     * HRD side: the assignee, otherwise every super admin and the firm's specialist.
     *
     * @return Collection<int, User>
     */
    private function staff(SupportTicket $ticket, User $actor): Collection
    {
        $users = $ticket->assignee?->is_active
            ? collect([$ticket->assignee])
            : self::staffOptions($ticket);

        return $users->reject(fn (User $user) => $user->is($actor))->values();
    }

    /**
     * Customer side: whoever opened the ticket.
     *
     * @return Collection<int, User>
     */
    private function customers(SupportTicket $ticket, User $actor): Collection
    {
        return collect([$ticket->user])->filter(fn (?User $user) => $user !== null && $user->is_active && ! $user->is($actor))->values();
    }
}
