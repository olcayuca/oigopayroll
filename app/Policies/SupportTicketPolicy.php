<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\TicketStatus;
use App\Enums\UserType;
use App\Models\Firm;
use App\Models\SupportTicket;
use App\Models\User;

/**
 * Destek talepleri. Firm users open and follow tickets in the panel; HRD answers them —
 * super admins in the admin portal (Gate::before), the firm's payroll specialist in the panel.
 */
class SupportTicketPolicy
{
    /**
     * HRD side of a firm's tickets: its responsible specialist, or a specialist with access to the firm.
     */
    public static function isStaff(User $user, Firm $firm): bool
    {
        return $user->isSuperAdmin() || ($user->type === UserType::PayrollSpecialist
            && ($firm->specialist_id === $user->id || $user->hasPermissionOn(Permission::FirmView, $firm)));
    }

    /**
     * Usage: $user->can('create', [SupportTicket::class, $firm])
     */
    public function create(User $user, Firm $firm): bool
    {
        return $firm->isActive() && $user->mayWorkInFirm($firm->id);
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $ticket->user_id === $user->id || $user->can('viewAllSupportTickets', $ticket->firm);
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        return $ticket->status !== TicketStatus::Closed && $this->view($user, $ticket);
    }

    /**
     * Change status / assignee (HRD only). The opener may only close their own ticket (close()).
     */
    public function manage(User $user, SupportTicket $ticket): bool
    {
        return self::isStaff($user, $ticket->firm);
    }

    public function close(User $user, SupportTicket $ticket): bool
    {
        return $ticket->status !== TicketStatus::Closed && ($ticket->user_id === $user->id || $this->manage($user, $ticket));
    }
}
