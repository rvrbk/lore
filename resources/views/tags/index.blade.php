<x-layouts.app title="Tags">
    <h1 class="text-2xl font-semibold">Tags</h1>
    <p class="mt-2 text-sm text-zinc-400">Add tags to pages and tasks to find related things quickly.</p>
    @if($tags->isEmpty())
        <p class="mt-6 text-sm text-zinc-400">No tags yet. Add some when editing a page or a task.</p>
    @else
        <ul class="mt-6 flex flex-wrap gap-2">
            @foreach($tags as $tag)
                <li><a href="{{ route('tags.show', $tag) }}" class="flex items-center gap-2 border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-sm hover:bg-zinc-800"><span class="text-indigo-300">#{{ $tag->name }}</span><span class="text-xs text-zinc-400">{{ $tag->pages_count }}p · {{ $tag->tasks_count }}t</span></a></li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
