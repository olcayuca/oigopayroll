<?php

use App\Actions\Support\ManageSupportTickets;
use App\Enums\Portal;
use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use App\Policies\SupportTicketPolicy;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/*
 * One support ticket, on both portals: the panel (firm users, the firm's specialist) and the admin portal (super admins).
 * Messages go both ways; HRD may set the status and the assignee, the opener may close the ticket.
 */
new #[Title('Destek Talebi')] class extends Component {
    use WithFileUploads;

    public SupportTicket $ticket;

    #[Locked]
    public bool $admin = false;

    public string $body = '';

    /** @var TemporaryUploadedFile|null */
    public $attachment = null;

    /** Status after an HRD reply. */
    public string $replyStatus = 'awaiting_customer';

    public function mount(SupportTicket $ticket): void
    {
        $this->authorize('view', $ticket);
        $this->admin = Portal::fromHost(request()->getHost()) === Portal::Admin;

        if ($this->admin) {
            $this->authorize('manage-settings');
        } elseif ($ticket->firm_id !== Auth::user()->current_firm_id && Auth::user()->mayWorkInFirm($ticket->firm_id)) {
            Auth::user()->switchFirm($ticket->firm);
        }

        $this->ticket = $ticket;
    }

    #[Computed]
    public function staff(): bool
    {
        return SupportTicketPolicy::isStaff(Auth::user(), $this->ticket->firm);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupportMessage>
     */
    #[Computed]
    public function messages(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->ticket->messages()->with('user:id,name')->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    #[Computed]
    public function staffOptions(): \Illuminate\Support\Collection
    {
        return ManageSupportTickets::staffOptions($this->ticket);
    }

    public function reply(ManageSupportTickets $tickets): void
    {
        $this->authorize('reply', $this->ticket);
        $status = $this->staff ? TicketStatus::tryFrom($this->replyStatus) : null;

        try {
            $tickets->reply($this->ticket, Auth::user(), $this->body, $this->attachment, $status);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->reset('body', 'attachment');
        $this->ticket->refresh();
        unset($this->messages);
        Flux::toast(variant: 'success', text: 'Mesaj gönderildi.');
    }

    public function setStatus(string $status, ManageSupportTickets $tickets): void
    {
        $this->authorize('manage', $this->ticket);
        $tickets->changeStatus($this->ticket, Auth::user(), TicketStatus::from($status));
        $this->ticket->refresh();
    }

    public function close(ManageSupportTickets $tickets): void
    {
        $this->authorize('close', $this->ticket);
        $tickets->changeStatus($this->ticket, Auth::user(), TicketStatus::Closed);
        $this->ticket->refresh();
        Flux::toast(variant: 'success', text: 'Talep kapatıldı.');
    }

    public function assign(string $userId, ManageSupportTickets $tickets): void
    {
        $this->authorize('manage', $this->ticket);
        $assignee = $userId === '' ? null : $this->staffOptions->firstWhere('id', (int) $userId);
        $tickets->assign($this->ticket, $assignee);
        $this->ticket->refresh();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ route($admin ? 'admin.support.index' : 'support.index') }}" wire:navigate class="text-[13px] font-bold text-brand hover:text-mint">‹ Destek talepleri</a>
            <h1 class="mt-1.5 text-[22px] font-extrabold tracking-tight text-ink">
                <span class="text-muted-2">{{ $ticket->number() }}</span> {{ $ticket->subject }}
            </h1>
            <div class="mt-1.5 flex flex-wrap items-center gap-2 text-[12.5px] text-muted">
                <x-panel.badge :color="$ticket->status->color()">{{ $ticket->status->label() }}</x-panel.badge>
                <x-panel.badge :color="$ticket->priority->color()" :dot="false">{{ $ticket->priority->label() }}</x-panel.badge>
                <span>{{ $ticket->category }} · {{ $ticket->module }}</span>
                @if ($admin) <span>· {{ $ticket->firm->name }}</span> @endif
            </div>
        </div>
        @can('close', $ticket)
            <flux:button icon="check-circle" wire:click="close" wire:confirm="Talep kapatılsın mı? Kapanan talebe yeni mesaj yazılamaz.">Talebi kapat</flux:button>
        @endcan
    </div>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0 rounded-2xl border border-line bg-white shadow-[0_1px_3px_rgba(16,40,72,0.04)]">
            <div class="flex flex-col gap-4 bg-[#F7F9FC] p-5" data-test="ticket-thread">
                @foreach ($this->messages as $message)
                    @php $mine = $message->user_id === auth()->id(); @endphp
                    <div wire:key="m-{{ $message->id }}" @class(['flex', 'justify-end' => $mine])>
                        <div @class([
                            'max-w-[85%] rounded-[14px] px-4 py-3 text-[13.5px] leading-relaxed',
                            'rounded-br-[4px] bg-brand text-white' => $mine,
                            'rounded-bl-[4px] border border-[#E8EDF3] bg-white text-ink' => ! $mine,
                        ])>
                            <div @class(['mb-1 text-[11.5px] font-bold', 'text-white/70' => $mine, 'text-mint-strong' => ! $mine && $message->from_staff, 'text-muted-2' => ! $mine && ! $message->from_staff])>
                                {{ $message->authorName() }} · {{ $message->created_at?->format('d.m.Y H:i') }}
                            </div>
                            <div class="whitespace-pre-line break-words">{{ $message->body }}</div>
                            @if ($message->attachment_path)
                                <a href="{{ route('support.attachment', $message) }}" @class(['mt-2 inline-flex items-center gap-1.5 text-[12.5px] font-bold underline', 'text-white' => $mine, 'text-brand' => ! $mine])>
                                    <flux:icon.paper-clip class="size-3.5" /> {{ $message->attachment_name }} ({{ \Illuminate\Support\Number::fileSize($message->attachment_size ?? 0) }})
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @can('reply', $ticket)
                <form wire:submit="reply" class="flex flex-col gap-3 border-t border-line-3 p-5" data-test="ticket-reply">
                    <flux:textarea wire:model="body" rows="4" placeholder="Yanıtınızı yazın…" aria-label="Mesaj" maxlength="{{ ManageSupportTickets::MAX_BODY }}" />
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-[220px] flex-1">
                            <flux:input type="file" wire:model="attachment" size="sm" aria-label="Ek dosya" />
                        </div>
                        @if ($this->staff)
                            <flux:select wire:model="replyStatus" size="sm" class="max-w-[220px]" aria-label="Yanıttan sonra durum">
                                <flux:select.option value="awaiting_customer">Yanıt bekleniyor</flux:select.option>
                                <flux:select.option value="in_progress">İnceleniyor</flux:select.option>
                                <flux:select.option value="resolved">Çözüldü</flux:select.option>
                            </flux:select>
                        @endif
                        <flux:button type="submit" variant="primary" icon="paper-airplane" wire:loading.attr="disabled" wire:target="reply,attachment">Gönder</flux:button>
                    </div>
                    @if ($ticket->status === \App\Enums\TicketStatus::Resolved && ! $this->staff)
                        <p class="text-[12px] text-muted">Talep çözüldü olarak işaretlendi. Yazarsanız yeniden açılır; sorun bittiyse "Talebi kapat" ile kapatabilirsiniz.</p>
                    @endif
                </form>
            @else
                <div class="border-t border-line-3 px-5 py-4 text-[13px] text-muted">Talep kapalı; yeni bir konu için yeni talep oluşturun.</div>
            @endcan
        </div>

        <div class="flex flex-col gap-4">
            <div class="rounded-2xl border border-line bg-white p-[18px] text-[13px]">
                <div class="mb-3 text-[12px] font-bold tracking-[0.04em] text-muted">TALEP BİLGİLERİ</div>
                <dl class="grid gap-2.5">
                    <div class="flex justify-between gap-3"><dt class="text-muted">Firma</dt><dd class="text-end font-semibold text-ink">{{ $ticket->firm->name }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Açan</dt><dd class="text-end font-semibold text-ink">{{ $ticket->user->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Açılış</dt><dd class="font-semibold text-ink">{{ $ticket->created_at?->format('d.m.Y H:i') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Son mesaj</dt><dd class="font-semibold text-ink">{{ $ticket->last_message_at?->diffForHumans() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">İlgilenen</dt><dd class="text-end font-semibold text-ink">{{ $ticket->assignee->name ?? 'Atanmadı' }}</dd></div>
                </dl>
            </div>

            @can('manage', $ticket)
                <div class="rounded-2xl border border-line bg-white p-[18px]" data-test="ticket-manage">
                    <div class="mb-3 text-[12px] font-bold tracking-[0.04em] text-muted">HRD İŞLEMLERİ</div>
                    <div class="flex flex-col gap-3">
                        <flux:select label="Durum" size="sm" wire:change="setStatus($event.target.value)">
                            @foreach (TicketStatus::cases() as $status)
                                <flux:select.option :value="$status->value" :selected="$ticket->status === $status">{{ $status->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select label="İlgilenen" size="sm" wire:change="assign($event.target.value)">
                            <flux:select.option value="">Atanmadı</flux:select.option>
                            @foreach ($this->staffOptions as $option)
                                <flux:select.option :value="$option->id" :selected="$ticket->assigned_to === $option->id">{{ $option->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
            @endcan
        </div>
    </div>
</div>
