<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-200 antialiased">
<header class="sticky top-0 z-30 flex items-center gap-3 border-b border-zinc-700 bg-zinc-900 px-4 py-2.5 lg:hidden">
    <button type="button" data-nav-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Open menu" class="-ml-1 border border-zinc-700 px-2.5 py-1 text-lg leading-none text-zinc-200">&#9776;</button>
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-pixel text-sm text-zinc-100"><img src="{{ asset('favicon.svg') }}" alt="" class="size-6" style="image-rendering: pixelated">Lore<span class="text-indigo-400">_</span></a>
    @if($dueCount || $downCount)<span class="ml-auto rounded-full bg-red-500 px-2 text-xs font-semibold text-white">{{ $dueCount + $downCount }}</span>@endif
</header>
<div data-nav-backdrop hidden class="fixed inset-0 z-40 bg-black/70 lg:hidden"></div>
<div class="flex min-h-screen">
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex h-screen w-72 max-w-[85vw] -translate-x-full flex-col gap-5 overflow-y-auto border-r border-zinc-800 bg-zinc-900 p-4 transition-transform duration-200 data-[open]:translate-x-0 lg:sticky lg:top-0 lg:z-auto lg:max-w-none lg:shrink-0 lg:translate-x-0">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 font-pixel text-base text-zinc-100"><img src="{{ asset('favicon.svg') }}" alt="" class="size-7" style="image-rendering: pixelated">Lore<span class="text-indigo-400">_</span></a>
        <form action="{{ route('search') }}">
            <input name="q" value="{{ request('q') }}" placeholder="Search pages…  ( / )" data-search-input class="w-full rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
        </form>
        <nav class="space-y-1 text-sm">
            <a href="{{ route('dashboard') }}" class="flex items-center rounded px-2 py-1.5 hover:bg-zinc-800 {{ request()->routeIs('dashboard') ? 'bg-zinc-800 font-medium text-zinc-100' : '' }}">Home</a>
            <a href="{{ route('tasks.index') }}" class="flex items-center justify-between rounded px-2 py-1.5 hover:bg-zinc-800 {{ request()->routeIs('tasks.*') ? 'bg-zinc-800 font-medium text-zinc-100' : '' }}">Board</a>
            <a href="{{ route('reminders.index') }}" class="flex items-center justify-between rounded px-2 py-1.5 hover:bg-zinc-800 {{ request()->routeIs('reminders.*') ? 'bg-zinc-800 font-medium text-zinc-100' : '' }}">
                Reminders
                @if($dueCount)<span class="rounded-full bg-red-500 px-2 text-xs font-semibold text-white">{{ $dueCount }}</span>@endif
            </a>
            <a href="{{ route('tags.index') }}" class="flex items-center rounded px-2 py-1.5 hover:bg-zinc-800 {{ request()->routeIs('tags.*') ? 'bg-zinc-800 font-medium text-zinc-100' : '' }}">Tags</a>
            <a href="{{ route('pointy.index') }}" class="flex items-center rounded px-2 py-1.5 hover:bg-zinc-800 {{ request()->routeIs('pointy.*') ? 'bg-zinc-800 font-medium text-zinc-100' : '' }}">Pointy</a>
            <a href="{{ route('deploy.index') }}" class="flex items-center rounded px-2 py-1.5 hover:bg-zinc-800 {{ request()->routeIs('deploy.*') ? 'bg-zinc-800 font-medium text-zinc-100' : '' }}">Deploy</a>
            <a href="{{ route('analytics.index') }}" class="flex items-center rounded px-2 py-1.5 hover:bg-zinc-800 {{ request()->routeIs('analytics.*') ? 'bg-zinc-800 font-medium text-zinc-100' : '' }}">Analytics</a>
            <a href="{{ route('monitors.index') }}" class="flex items-center justify-between rounded px-2 py-1.5 hover:bg-zinc-800 {{ request()->routeIs('monitors.*') ? 'bg-zinc-800 font-medium text-zinc-100' : '' }}">
                Server health
                @if($downCount)<span class="rounded-full bg-red-500 px-2 text-xs font-semibold text-white">{{ $downCount }}</span>@endif
            </a>
            <a href="{{ route('incidents.index') }}" class="flex items-center justify-between rounded px-2 py-1.5 hover:bg-zinc-800 {{ request()->routeIs('incidents.*') ? 'bg-zinc-800 font-medium text-zinc-100' : '' }}">
                Incidents
                @if($openIncidents->isNotEmpty())<span class="rounded-full bg-red-500 px-2 text-xs font-semibold text-white">{{ $openIncidents->count() }}</span>@endif
            </a>
        </nav>
        @foreach(['Pinned' => $pinnedPages, 'Recent' => $recentPages] as $heading => $list)
            @if($list->isNotEmpty())
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ $heading }}</div>
                    <nav class="mt-2 space-y-0.5 text-sm">
                        @foreach($list as $p)
                            <a href="{{ route('pages.show', $p) }}" title="{{ $p->title }}" class="block truncate rounded px-2 py-1 hover:bg-zinc-800 {{ isset($page) && $page?->is($p) ? 'bg-zinc-800 font-medium text-zinc-100' : '' }}">{{ $p->title }}</a>
                        @endforeach
                    </nav>
                </div>
            @endif
        @endforeach
        <div class="flex-1">
            <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-zinc-400">
                Spaces <a href="{{ route('spaces.create') }}" class="text-base leading-none hover:text-zinc-100" title="New space">+</a>
            </div>
            <nav class="mt-2 space-y-1 text-sm">
                @foreach($navSpaces as $s)
                    @php $isActive = isset($space) && $space->is($s); @endphp
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
        <form method="POST" action="{{ route('logout') }}" class="sticky bottom-0 -mb-4 flex shrink-0 items-center gap-2 border-t border-zinc-800 bg-zinc-900 pb-4 pt-3 text-sm">
            @csrf
            <span class="min-w-0 flex-1 truncate text-zinc-400" title="Signed in as {{ auth()->user()->username }}">{{ auth()->user()->username }}</span>
            <button type="button" data-palette-open class="border border-zinc-700 px-1.5 py-0.5 text-xs text-zinc-400 hover:text-zinc-100" title="Quick switcher">Ctrl+K</button>
            <button class="text-zinc-400 hover:text-zinc-100">Log out</button>
        </form>
    </aside>
    <main class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
        <div>
            @if($openIncidents->isNotEmpty() && ! request()->routeIs('incidents.*'))
                <div role="alert" class="mb-6 border border-red-500/60 bg-red-500/10 p-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-semibold text-red-300">{{ $openIncidents->count() }} open incident{{ $openIncidents->count() === 1 ? '' : 's' }}</span>
                        <a href="{{ route('incidents.index') }}" class="text-red-300 underline hover:text-red-200">Review</a>
                    </div>
                    <ul class="mt-1 space-y-0.5 text-zinc-300">
                        @foreach($openIncidents->take(4) as $incident)
                            <li class="truncate">🐞 {{ $incident->title }} <span class="text-zinc-500">· for {{ $incident->duration() }}</span></li>
                        @endforeach
                        @if($openIncidents->count() > 4)<li class="text-zinc-500">and {{ $openIncidents->count() - 4 }} more</li>@endif
                    </ul>
                </div>
            @endif
            {{ $slot }}
        </div>
    </main>
</div>

@php
    $paletteActions = array_values(array_filter([
        ['title' => 'New task', 'hint' => 'Board', 'url' => route('tasks.index', ['new' => 1])],
        ['title' => 'New space', 'hint' => 'Action', 'url' => route('spaces.create')],
        ['title' => 'Home', 'hint' => 'Go to', 'url' => route('dashboard')],
        ['title' => 'Board', 'hint' => 'Go to', 'url' => route('tasks.index')],
        ['title' => 'Reminders', 'hint' => 'Go to', 'url' => route('reminders.index')],
        ['title' => 'Tags', 'hint' => 'Go to', 'url' => route('tags.index')],
        ['title' => 'Deploy', 'hint' => 'Go to', 'url' => route('deploy.index')],
        ['title' => 'Analytics', 'hint' => 'Go to', 'url' => route('analytics.index')],
        ['title' => 'Server health', 'hint' => 'Go to', 'url' => route('monitors.index')],
        ['title' => 'Incidents', 'hint' => 'Go to', 'url' => route('incidents.index')],
        isset($space) ? ['title' => 'New page in '.$space->name, 'hint' => 'Action', 'url' => route('pages.create', $space)] : null,
    ]));
@endphp
<dialog data-palette data-url="{{ route('palette') }}" data-actions="{{ json_encode($paletteActions) }}" class="m-auto mt-[12vh] w-full max-w-xl border border-zinc-700 bg-zinc-900 p-0 text-zinc-200 backdrop:bg-black/70">
    <input data-palette-input placeholder="Jump to a page or action…" autocomplete="off" class="w-full border-0 border-b border-zinc-700 bg-transparent px-4 py-3 text-base focus:ring-0">
    <ul data-palette-list class="max-h-80 overflow-y-auto py-1 text-sm"></ul>
    <div class="border-t border-zinc-800 px-4 py-1.5 text-xs text-zinc-500">↑↓ to move · Enter to open · Esc to close</div>
</dialog>
</body>
</html>
