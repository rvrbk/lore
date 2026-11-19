<x-layouts.app :title="'History: '.$page->title">
    <a href="{{ route('pages.show', $page) }}" class="text-sm text-zinc-400 hover:underline">← {{ $page->title }}</a>
    <h1 class="mt-1 text-2xl font-semibold">History</h1>
    <ul class="mt-6 space-y-3">
        @foreach($revisions as $rev)
            <li class="rounded-lg border border-zinc-800 bg-zinc-900 p-3">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium">{{ $rev->title }} <span class="font-normal text-zinc-400">· {{ $rev->created_at->format('j M Y H:i') }}</span></span>
                    <form method="POST" action="{{ route('pages.restore', [$page, $rev->id]) }}" onsubmit="return confirm('Restore this version?')">@csrf<button class="rounded border px-2 py-1 text-xs hover:bg-zinc-800">Restore</button></form>
                </div>
                <details class="mt-2"><summary class="cursor-pointer text-xs text-zinc-400">Show content</summary><pre class="mt-2 whitespace-pre-wrap text-xs">{{ $rev->body }}</pre></details>
            </li>
        @endforeach
    </ul>
</x-layouts.app>
