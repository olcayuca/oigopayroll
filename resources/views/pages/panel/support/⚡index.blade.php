<?php

use App\Actions\Support\ManageSupportTickets;
use App\Enums\TicketPriority;
use App\Livewire\PanelComponent;
use App\Models\SupportTicket;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/*
 * Destek: the firm's support tickets (own ones; all for managers and HRD) and the new-ticket form.
 * New tickets reach HRD in the admin portal and the firm's payroll specialist, by bell and e-mail.
 */
new #[Title('Destek')] class extends PanelComponent {
    use WithFileUploads, WithPagination;

    #[Url(as: 'sekme', except: 'acik')]
    public string $tab = 'acik';

    public string $subject = '';

    public string $category = 'Kurulum';

    public string $module = 'Şirketler & İşyerleri';

    public string $priority = 'normal';

    public string $body = '';

    /** @var TemporaryUploadedFile|null */
    public $attachment = null;

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, SupportTicket>
     */
    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        return SupportTicket::query()->visibleInPanel(Auth::user(), $this->firm)
            ->active($this->tab === 'acik')
            ->with('user:id,name')->withCount('messages')
            ->latest('last_message_at')->latest('id')
            ->paginate(15);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $query = fn () => SupportTicket::query()->visibleInPanel(Auth::user(), $this->firm);

        return ['acik' => $query()->active()->count(), 'gecmis' => $query()->active(false)->count()];
    }

    public function send(ManageSupportTickets $tickets): void
    {
        $this->authorize('create', [SupportTicket::class, $this->firm]);

        try {
            $ticket = $tickets->open($this->firm, Auth::user(), [
                'subject' => $this->subject, 'category' => $this->category, 'module' => $this->module,
                'priority' => $this->priority, 'body' => $this->body,
            ], $this->attachment);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->reset('subject', 'body', 'attachment');
        Flux::toast(variant: 'success', text: "Talep {$ticket->number()} oluşturuldu. Yanıtlandığında bildirim alacaksınız.");
        $this->redirectRoute('support.show', $ticket, navigate: true);
    }
}; ?>

<div>
    <x-panel.page-header :crumbs="['Yönetim' => null, 'Destek' => null]" title="Destek"
        subtitle="Talep oluşturun, açık taleplerinizi takip edin. Talepler HRD destek ekibine ve sorumlu bordro uzmanınıza iletilir; yanıtlar bildirim ve e-postayla gelir." />

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="min-w-0 rounded-2xl border border-line bg-white shadow-[0_1px_3px_rgba(16,40,72,0.04)]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line-3 px-5 py-4">
                <x-panel.segmented model="tab" :current="$tab"
                    :options="['acik' => 'Açık talepler ('.$this->counts['acik'].')', 'gecmis' => 'Geçmiş ('.$this->counts['gecmis'].')']" />
            </div>

            <div class="divide-y divide-line-4">
                @forelse ($this->tickets as $ticket)
                    <a href="{{ route('support.show', $ticket) }}" wire:navigate wire:key="t-{{ $ticket->id }}"
                        class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 transition hover:bg-row-hover" data-test="ticket-row">
                        <span class="w-14 shrink-0 text-[12.5px] font-extrabold text-muted-2 tabular-nums">{{ $ticket->number() }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[14px] font-bold text-ink">{{ $ticket->subject }}</span>
                            <span class="mt-0.5 block text-[12px] text-muted">
                                {{ $ticket->category }} · {{ $ticket->module }} · {{ $ticket->last_message_at?->diffForHumans() }}
                                @if ($ticket->user && $ticket->user_id !== auth()->id()) · {{ $ticket->user->name }} @endif
                                · {{ $ticket->messages_count }} mesaj
                            </span>
                        </span>
                        <x-panel.badge :color="$ticket->priority->color()" :dot="false">{{ $ticket->priority->label() }}</x-panel.badge>
                        <x-panel.badge :color="$ticket->status->color()">{{ $ticket->status->label() }}</x-panel.badge>
                    </a>
                @empty
                    <x-panel.empty icon="lifebuoy" :title="$tab === 'acik' ? 'Açık talep yok' : 'Geçmiş talep yok'">
                        Yeni bir talebi formdan oluşturabilirsiniz.
                    </x-panel.empty>
                @endforelse
            </div>

            @if ($this->tickets->hasPages())
                <div class="border-t border-line-3 px-5 py-3">{{ $this->tickets->links('partials.panel-pagination') }}</div>
            @endif
        </div>

        @can('create', [\App\Models\SupportTicket::class, $this->firm])
            <form wire:submit="send" class="rounded-2xl border border-line bg-white p-5 shadow-[0_1px_3px_rgba(16,40,72,0.04)]" data-test="new-ticket">
                <h2 class="text-[15px] font-extrabold text-ink">Yeni destek talebi</h2>
                <p class="mt-1 mb-4 text-[12.5px] text-muted">Ekranı ve adımları ne kadar net anlatırsanız o kadar hızlı çözeriz.</p>

                <div class="flex flex-col gap-4">
                    <flux:input wire:model="subject" label="Konu" required maxlength="160" />
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                        <flux:select wire:model="category" label="Kategori">
                            @foreach (SupportTicket::CATEGORIES as $option)
                                <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="module" label="Modül">
                            @foreach (SupportTicket::MODULES as $option)
                                <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div>
                        <div class="mb-2 text-sm font-medium text-ink-2">Öncelik</div>
                        <x-panel.segmented model="priority" :current="$priority" :options="TicketPriority::options()" />
                    </div>
                    <flux:textarea wire:model="body" label="Açıklama" rows="5" required maxlength="{{ ManageSupportTickets::MAX_BODY }}"
                        placeholder="Ne yapmaya çalışıyordunuz, ne oldu, ne bekliyordunuz?" />
                    <flux:input type="file" wire:model="attachment" label="Ek dosya" description="Ekran görüntüsü, Excel veya PDF; en fazla 10 MB." />
                    <p class="text-[11.5px] text-muted-2">TCKN, IBAN ve şifre gibi bilgileri talebe yazmayın.</p>
                    <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="send,attachment">Talebi gönder</flux:button>
                </div>
            </form>
        @endcan
    </div>
</div>
