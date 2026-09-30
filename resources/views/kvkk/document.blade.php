<!DOCTYPE html>
<html lang="tr">
    <head>
        @include('partials.head', ['title' => $document->title])
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-900">
        <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
            <p class="text-sm text-zinc-500">
                {{ $document->type->label() }} · Sürüm {{ $document->version }} · {{ $document->published_at?->format('d.m.Y') }}
            </p>
            <h1 class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">{{ $document->title }}</h1>
            <x-policy-body :document="$document" class="mt-6" />
        </main>
    </body>
</html>
