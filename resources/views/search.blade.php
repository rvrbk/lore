<x-layouts.app title="Search">
    <h1 class="text-2xl font-semibold">Search{{ $q !== '' ? ': '.$q : '' }}</h1>
    <ul class="mt-6 space-y-3">
        @forelse($results as $p)
            <li class="rounded-lg border border-zinc-800 bg-zinc-900 p-3">
                <a href="{{ route('pages.show', $p) }}" class="font-medium text-indigo-300 hover:underline">{{ $p->title }}</a>
                <span class="text-xs text-zinc-400">· {{ $p->space->name }}</span>
                <p class="mt-1 text-sm text-zinc-400">{{ $p->snippet($q) }}</p>
            </li>
        @empty
            <li class="text-sm text-zinc-400">{{ $q === '' ? 'Type something to search.' : 'No pages match.' }}</li>
        @endforelse
    </ul>
</x-layouts.app>
