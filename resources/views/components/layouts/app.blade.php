<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-200 antialiased">
<div class="flex min-h-screen">
    <aside class="sticky top-0 flex h-screen w-72 shrink-0 flex-col gap-5 overflow-y-auto border-r border-zinc-800 bg-zinc-900 p-4">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 font-pixel text-base text-zinc-100"><img src="{{ asset('favicon.svg') }}" alt="" class="size-7" style="image-rendering: pixelated">Lore<span class="text-indigo-400">_</span></a>
        <form action="{{ route('search') }}">
            <input name="q" value="{{ request('q') }}" placeholder="Search pages…" class="w-full rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
        </form>
        <nav class="space-y-1 text-sm">
            <a href="{{ route('dashboard') }}" class="flex items-center rounded px-2 py-1.5 hover:bg-zinc-800 {{ request()->routeIs('dashboard') ? 'bg-zinc-800 font-medium text-zinc-100' : '' }}">Home</a>
            <a href="{{ route('tasks.index') }}" class="flex items-center justify-between rounded px-2 py-1.5 hover:bg-zinc-800 {{ request()->routeIs('tasks.*') ? 'bg-zinc-800 font-medium text-zinc-100' : '' }}">Board</a>
            <a href="{{ route('reminders.index') }}" class="flex items-center justify-between rounded px-2 py-1.5 hover:bg-zinc-800 {{ request()->routeIs('reminders.*') ? 'bg-zinc-800 font-medium text-zinc-100' : '' }}">
                Reminders
                @if($dueCount)<span class="rounded-full bg-red-500 px-2 text-xs font-semibold text-white">{{ $dueCount }}</span>@endif
            </a>
            <a href="{{ route('monitors.index') }}" class="flex items-center justify-between rounded px-2 py-1.5 hover:bg-zinc-800 {{ request()->routeIs('monitors.*') ? 'bg-zinc-800 font-medium text-zinc-100' : '' }}">
                Server health
                @if($downCount)<span class="rounded-full bg-red-500 px-2 text-xs font-semibold text-white">{{ $downCount }}</span>@endif
            </a>
        </nav>
        <div class="min-h-0 flex-1">
            <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-zinc-400">
                Spaces <a href="{{ route('spaces.create') }}" class="text-base leading-none hover:text-zinc-100" title="New space">+</a>
            </div>
            <nav class="mt-2 space-y-1 text-sm">
                @foreach($navSpaces as $s)
                    @php($isActive = isset($space) && $space->is($s))
                    <a href="{{ route('spaces.show', $s) }}" class="block rounded px-2 py-1.5 hover:bg-zinc-800 {{ $isActive ? 'font-medium text-zinc-100' : '' }}">{{ $s->name }}</a>
                    @if($isActive)
                        <div class="mb-2 ml-2 border-l border-zinc-800 pl-2">
                            @include('partials.tree', ['byParent' => $space->tree(), 'parentId' => '', 'current' => $page ?? null])
                            <a href="{{ route('pages.create', $space) }}" class="mt-1 block rounded px-2 py-1 text-sm text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100">+ New page</a>
                        </div>
                    @endif
                @endforeach
            </nav>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="flex items-center justify-between border-t border-zinc-800 pt-3 text-sm">
            @csrf
            <span class="truncate text-zinc-400" title="Signed in as {{ auth()->user()->username }}">{{ auth()->user()->username }}</span>
            <button class="text-zinc-400 hover:text-zinc-100">Log out</button>
        </form>
    </aside>
    <main class="min-w-0 flex-1 p-8">
        <div class="mx-auto max-w-4xl">{{ $slot }}</div>
    </main>
</div>
</body>
</html>
