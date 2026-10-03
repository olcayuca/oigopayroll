@props([
    'title',
    'description',
])

@if (\App\Enums\Portal::fromHost(request()->getHost()) === \App\Enums\Portal::Panel)
    <div class="flex w-full flex-col">
        <h1 class="text-[25px] leading-tight font-extrabold tracking-[-0.02em] text-ink">{{ $title }}</h1>
        <p class="mt-1.5 text-[13.5px] text-muted">{{ $description }}</p>
    </div>
@else
    <div class="flex w-full flex-col text-center">
        <flux:heading size="xl">{{ $title }}</flux:heading>
        <flux:subheading>{{ $description }}</flux:subheading>
    </div>
@endif
