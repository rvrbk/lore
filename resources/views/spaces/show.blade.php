<x-layouts.app :title="$space->name" :space="$space">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-semibold">{{ $space->name }}</h1>
            @if($space->description)<p class="mt-1 text-zinc-400">{{ $space->description }}</p>@endif
        </div>
        <div class="flex gap-2 text-sm">
            <a href="{{ route('export.space', $space) }}" class="rounded border px-3 py-1.5 hover:bg-zinc-800" title="Download this space as Markdown files in a .zip">Export</a>
            <a href="{{ route('spaces.edit', $space) }}" class="rounded border px-3 py-1.5 hover:bg-zinc-800">Edit</a>
            <a href="{{ route('pages.create', $space) }}" class="rounded bg-indigo-600 px-3 py-1.5 font-medium text-white hover:bg-indigo-700">New page</a>
        </div>
    </div>
    <div class="mt-6 rounded-lg border border-zinc-800 bg-zinc-900 p-3">
        @if($tree->isEmpty())
            <p class="text-sm text-zinc-400">No pages yet.</p>
        @else
            @include('partials.tree', ['byParent' => $tree, 'parentId' => '', 'current' => null])
        @endif
    </div>
</x-layouts.app>
