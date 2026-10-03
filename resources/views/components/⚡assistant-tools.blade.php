<?php

use App\Models\Firm;
use App\Models\UserNote;
use App\Models\UserReminder;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * Assistant shortcuts, opened as popups: personal notes, reminders (to the notification bell), calculator.
 * Notes and reminders belong to the user and are kept per active firm.
 */
new class extends Component {
    public string $noteBody = '';

    public ?int $editingNoteId = null;

    public string $reminderTitle = '';

    public string $reminderAt = '';

    public string $reminderNote = '';

    #[Computed]
    public function firm(): ?Firm
    {
        return Auth::user()?->activeFirm();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, UserNote>
     */
    #[Computed]
    public function notes(): \Illuminate\Database\Eloquent\Collection
    {
        return UserNote::ownedBy(Auth::user(), $this->firm)->latest('updated_at')->latest('id')->limit(50)->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, UserReminder>
     */
    #[Computed]
    public function upcoming(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->reminders()->whereNull('notified_at')->orderBy('remind_at')->limit(50)->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, UserReminder>
     */
    #[Computed]
    public function past(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->reminders()->whereNotNull('notified_at')->latest('remind_at')->limit(5)->get();
    }

    public function saveNote(): void
    {
        $this->validate(['noteBody' => ['required', 'string', 'max:'.UserNote::MAX_LENGTH]], [], ['noteBody' => 'Not']);

        $note = $this->editingNoteId ? $this->note($this->editingNoteId) : new UserNote(['user_id' => Auth::id(), 'firm_id' => $this->firm?->id]);
        $note->body = trim($this->noteBody);
        $note->save();

        $this->reset('noteBody', 'editingNoteId');
        unset($this->notes);
        Flux::toast(variant: 'success', text: 'Not kaydedildi.');
    }

    public function editNote(int $id): void
    {
        $this->editingNoteId = $id;
        $this->noteBody = $this->note($id)->body;
    }

    public function cancelNote(): void
    {
        $this->reset('noteBody', 'editingNoteId');
        $this->resetValidation();
    }

    public function deleteNote(int $id): void
    {
        $this->note($id)->delete();

        if ($this->editingNoteId === $id) {
            $this->cancelNote();
        }

        unset($this->notes);
    }

    /**
     * Quick times: "1h", "tomorrow", "monday".
     */
    public function preset(string $when): void
    {
        $at = match ($when) {
            '1h' => now()->addHour()->startOfMinute(),
            'tomorrow' => now()->addDay()->setTime(9, 0),
            default => now()->next(Carbon::MONDAY)->setTime(9, 0),
        };

        $this->reminderAt = $at->format('Y-m-d\TH:i');
    }

    public function saveReminder(): void
    {
        $this->validate([
            'reminderTitle' => ['required', 'string', 'max:160'],
            'reminderAt' => ['required', 'date', 'after:now'],
            'reminderNote' => ['nullable', 'string', 'max:1000'],
        ], ['reminderAt.after' => 'Hatırlatma zamanı ileri bir tarih olmalı.'], [
            'reminderTitle' => 'Başlık', 'reminderAt' => 'Zaman', 'reminderNote' => 'Not',
        ]);

        UserReminder::create([
            'user_id' => Auth::id(),
            'firm_id' => $this->firm?->id,
            'title' => trim($this->reminderTitle),
            'note' => filled($this->reminderNote) ? trim($this->reminderNote) : null,
            'remind_at' => Carbon::parse($this->reminderAt),
        ]);

        $this->reset('reminderTitle', 'reminderAt', 'reminderNote');
        unset($this->upcoming);
        Flux::toast(variant: 'success', text: 'Hatırlatıcı eklendi; zamanı gelince bildirimlerde görünecek.');
    }

    public function deleteReminder(int $id): void
    {
        $this->reminders()->whereKey($id)->firstOrFail()->delete();
        unset($this->upcoming, $this->past);
    }

    private function note(int $id): UserNote
    {
        return UserNote::ownedBy(Auth::user(), $this->firm)->whereKey($id)->firstOrFail();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<UserReminder>
     */
    private function reminders(): \Illuminate\Database\Eloquent\Builder
    {
        return UserReminder::query()->where('user_id', Auth::id())->where('firm_id', $this->firm?->id);
    }
}; ?>

<div>
    {{-- Notes --}}
    <flux:modal name="assistant-notes" class="w-full md:w-[30rem]">
        <div class="space-y-4" data-test="assistant-notes">
            <div>
                <flux:heading size="lg">Notlarım</flux:heading>
                <flux:text class="mt-1">Yalnızca siz görürsünüz{{ $this->firm ? ' · '.$this->firm->name : '' }}.</flux:text>
            </div>

            <form wire:submit="saveNote" class="space-y-3">
                <flux:textarea wire:model="noteBody" rows="3" placeholder="Notunuzu yazın…" maxlength="{{ UserNote::MAX_LENGTH }}" aria-label="Not" />
                <div class="flex justify-end gap-2">
                    @if ($editingNoteId)
                        <flux:button size="sm" variant="filled" wire:click="cancelNote">Vazgeç</flux:button>
                    @endif
                    <flux:button size="sm" type="submit" variant="primary">{{ $editingNoteId ? 'Notu güncelle' : 'Not ekle' }}</flux:button>
                </div>
            </form>

            <div class="max-h-[320px] space-y-2 overflow-y-auto">
                @forelse ($this->notes as $note)
                    <div wire:key="note-{{ $note->id }}" @class(['group rounded-[10px] border px-3 py-2.5', 'border-brand bg-[#F4F8FD]' => $editingNoteId === $note->id, 'border-line-3 bg-white' => $editingNoteId !== $note->id])>
                        <div class="whitespace-pre-line break-words text-[13px] leading-relaxed text-ink">{{ $note->body }}</div>
                        <div class="mt-1.5 flex items-center justify-between gap-2">
                            <span class="text-[11px] font-semibold text-faint">{{ $note->updated_at?->format('d.m.Y H:i') }}</span>
                            <span class="flex gap-3 text-[11.5px] font-bold">
                                <button type="button" wire:click="editNote({{ $note->id }})" class="text-brand hover:text-mint">Düzenle</button>
                                <button type="button" wire:click="deleteNote({{ $note->id }})" wire:confirm="Not silinsin mi?" class="text-st-red-dot hover:underline">Sil</button>
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="rounded-[10px] border border-dashed border-line-2 px-3 py-6 text-center text-[13px] text-muted">Henüz not yok.</div>
                @endforelse
            </div>
        </div>
    </flux:modal>

    {{-- Reminders --}}
    <flux:modal name="assistant-reminders" class="w-full md:w-[30rem]">
        <div class="space-y-4" data-test="assistant-reminders">
            <div>
                <flux:heading size="lg">Hatırlatıcılar</flux:heading>
                <flux:text class="mt-1">Zamanı gelince bildirimlerinize (zil) düşer.</flux:text>
            </div>

            <form wire:submit="saveReminder" class="space-y-3">
                <flux:input wire:model="reminderTitle" label="Başlık" placeholder="Ör. SGK işe giriş bildirgesini kontrol et" maxlength="160" />
                <div>
                    <flux:input type="datetime-local" wire:model="reminderAt" label="Zaman" />
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach (['1h' => '1 saat sonra', 'tomorrow' => 'Yarın 09:00', 'monday' => 'Pazartesi 09:00'] as $key => $label)
                            <button type="button" wire:click="preset('{{ $key }}')"
                                class="rounded-full border border-line-2 bg-white px-2.5 py-1 text-[11.5px] font-bold text-brand hover:border-brand">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <flux:textarea wire:model="reminderNote" label="Not" rows="2" maxlength="1000" />
                <div class="flex justify-end">
                    <flux:button size="sm" type="submit" variant="primary">Hatırlatıcı ekle</flux:button>
                </div>
            </form>

            <div class="max-h-[260px] space-y-2 overflow-y-auto">
                @forelse ($this->upcoming as $reminder)
                    <div wire:key="r-{{ $reminder->id }}" class="flex items-start gap-3 rounded-[10px] border border-line-3 px-3 py-2.5">
                        <flux:icon.clock class="mt-0.5 size-4 shrink-0 text-mint" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-[13px] font-bold text-ink">{{ $reminder->title }}</div>
                            <div class="text-[11.5px] font-semibold text-muted">{{ $reminder->remind_at->format('d.m.Y H:i') }} · {{ $reminder->remind_at->diffForHumans() }}</div>
                            @if (filled($reminder->note))
                                <div class="mt-1 line-clamp-2 text-[12px] text-ink-4">{{ $reminder->note }}</div>
                            @endif
                        </div>
                        <button type="button" wire:click="deleteReminder({{ $reminder->id }})" wire:confirm="Hatırlatıcı silinsin mi?"
                            class="text-[11.5px] font-bold text-st-red-dot hover:underline">Sil</button>
                    </div>
                @empty
                    <div class="rounded-[10px] border border-dashed border-line-2 px-3 py-6 text-center text-[13px] text-muted">Bekleyen hatırlatıcı yok.</div>
                @endforelse

                @foreach ($this->past as $reminder)
                    <div wire:key="rp-{{ $reminder->id }}" class="flex items-center gap-3 rounded-[10px] bg-[#F7F9FC] px-3 py-2 opacity-70">
                        <flux:icon.check-circle class="size-4 shrink-0 text-faint" />
                        <div class="min-w-0 flex-1 truncate text-[12px] font-semibold text-ink-4">{{ $reminder->title }} · {{ $reminder->remind_at->format('d.m.Y H:i') }}</div>
                        <button type="button" wire:click="deleteReminder({{ $reminder->id }})" class="text-[11px] font-bold text-faint hover:text-st-red-dot">Kaldır</button>
                    </div>
                @endforeach
            </div>
        </div>
    </flux:modal>

    {{-- Calculator (in the browser; nothing is sent to the server) --}}
    <flux:modal name="assistant-calculator" class="w-full md:w-[21rem]">
        <div x-data="oigoCalculator" x-on:keydown.window="key($event)" data-test="assistant-calculator">
            <flux:heading size="lg">Hesap Makinesi</flux:heading>

            <div class="mt-4 rounded-[12px] bg-gradient-to-r from-[#0E2038] to-[#143a5e] px-4 py-3 text-end">
                <div class="h-4 truncate text-[12px] font-semibold text-[#9DB2CF]" x-text="expression"></div>
                <div class="mt-1 truncate text-[28px] font-extrabold tabular-nums text-white" x-text="display" data-test="calculator-display"></div>
            </div>

            <div class="mt-3 grid grid-cols-4 gap-2">
                <template x-for="button in buttons" :key="button.label">
                    <button type="button" x-on:click="press(button.value)" x-text="button.label"
                        class="h-12 rounded-[10px] text-[16px] font-bold transition active:scale-95"
                        :class="{
                            'bg-brand text-white hover:bg-[#1d4c82]': button.kind === 'op',
                            'bg-mint text-white hover:brightness-95': button.kind === 'eq',
                            'bg-[#EEF2F7] text-ink-2 hover:bg-[#E3E9F1]': button.kind === 'fn',
                            'border border-line-3 bg-white text-ink hover:border-line-2': ! button.kind,
                        }"></button>
                </template>
            </div>

            <div class="mt-3 flex items-center justify-between">
                <button type="button" x-on:click="copy()" class="text-[12px] font-bold text-brand hover:text-mint" x-text="copied ? 'Kopyalandı ✓' : 'Sonucu kopyala'"></button>
                <span class="text-[11px] font-semibold text-faint">Klavye ile de kullanılabilir</span>
            </div>

            <template x-if="history.length">
                <div class="mt-3 space-y-1 border-t border-line-3 pt-3">
                    <template x-for="(line, index) in history" :key="index">
                        <div class="flex justify-between text-[12px] text-ink-4"><span x-text="line.expression"></span><span class="font-bold text-ink" x-text="line.result"></span></div>
                    </template>
                </div>
            </template>
        </div>
    </flux:modal>
</div>
