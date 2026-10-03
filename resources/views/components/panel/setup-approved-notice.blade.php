@props(['firm'])

{{-- Shown on setup forms once the payroll specialist has approved the firm's setup. --}}
<div>
    @if ($firm?->setup_approved_at)
        <x-panel.alert variant="warning" class="mb-5" data-test="setup-approved-notice">
            Bu firmanın kurulumu {{ $firm->setup_approved_at->format('d.m.Y') }} tarihinde onaylandı. SGK sicil, vergi, ücret ve banka
            bilgilerindeki değişiklikler bordroyu etkiler; değişikliği kaydettikten sonra bordro uzmanınıza bildirin.
        </x-panel.alert>
    @endif
</div>
