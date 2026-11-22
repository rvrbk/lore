<x-layouts.app title="Page graveyard">
    <h1 class="text-2xl font-semibold">Page graveyard 🪦</h1>
    <p class="mt-2 text-sm text-zinc-400">Pages nobody edited for {{ \App\Http\Controllers\GraveyardController::EDITED_MONTHS }} months or opened for {{ \App\Http\Controllers\GraveyardController::VIEWED_MONTHS }}. Revive one to keep it, or bury it for good.</p>

    <ul class="mt-6 divide-y divide-zinc-800 border border-zinc-800 bg-zinc-900 text-sm">
        @forelse($pages as $page)
            <li class="flex flex-wrap items-center justify-between gap-3 px-3 py-2">
                <div class="min-w-0">
                    <a href="{{ route('pages.show', $page) }}" class="font-medium hover:underline">{{ $page->title }}</a>
                    <div class="text-xs text-zinc-400">{{ $page->space->name }} · edited {{ $page->updated_at->diffForHumans() }} · {{ $page->viewed_at ? 'opened '.$page->viewed_at->diffForHumans() : 'never opened' }}</div>
                </div>
                <div class="flex gap-2 text-xs">
                    <form method="POST" action="{{ route('graveyard.revive', $page) }}">@csrf<button class="rounded border px-2 py-1 hover:bg-zinc-800">Revive</button></form>
                    <form method="POST" action="{{ route('graveyard.bury', $page) }}" onsubmit="return confirm('Bury {{ e($page->title) }} for good? This deletes the page.')">@csrf @method('DELETE')<button class="rounded border border-red-500/40 px-2 py-1 text-red-400 hover:bg-red-500/10">Bury</button></form>
                </div>
            </li>
        @empty
            <li class="px-3 py-3 text-zinc-400">Nothing here. Every page has been touched lately. 🎉</li>
        @endforelse
    </ul>
</x-layouts.app>
