<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-200 antialiased">
<main class="flex min-h-screen items-center justify-center p-6">
    <div class="w-full max-w-sm">
        <div class="mb-6 flex items-center gap-2.5 font-pixel text-base text-zinc-100">
            <img src="{{ asset('favicon.svg') }}" alt="" class="size-7" style="image-rendering: pixelated">Lore<span class="text-indigo-400">_</span>
        </div>
        <div class="border border-zinc-800 bg-zinc-900 p-6">{{ $slot }}</div>
    </div>
</main>
</body>
</html>
