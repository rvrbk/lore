<x-layouts.app :title="$page->exists ? 'Edit '.$page->title : 'New page'" :space="$space" :page="$page->exists ? $page : null">
    <h1 class="text-2xl font-semibold">{{ $page->exists ? 'Edit page' : 'New page in '.$space->name }}</h1>
    <form method="POST" action="{{ $page->exists ? route('pages.update', $page) : route('pages.store', $space) }}" class="mt-6 space-y-4">
        @csrf
        @if($page->exists) @method('PUT') @endif
        <input name="title" value="{{ old('title', $page->title) }}" placeholder="Title" required class="w-full rounded-md border border-zinc-700 px-3 py-2 text-lg font-medium">
        <select name="parent_id" class="w-full rounded-md border border-zinc-700 px-3 py-2 text-sm">
            <option value="">Top level</option>
            @foreach($parents as $id => $label)
                <option value="{{ $id }}" @selected(old('parent_id', $page->parent_id) == $id)>{{ $label }}</option>
            @endforeach
        </select>
        <textarea name="body" rows="24" placeholder="Write in Markdown…" class="w-full rounded-md border border-zinc-700 px-3 py-2 font-mono text-sm">{{ old('body', $page->body) }}</textarea>
        @if($errors->any())<p class="text-sm text-red-400">{{ $errors->first() }}</p>@endif
        <div class="flex gap-3">
            <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button>
            <a href="{{ $page->exists ? route('pages.show', $page) : route('spaces.show', $space) }}" class="px-4 py-2 text-sm text-zinc-400">Cancel</a>
        </div>
    </form>
    @if($page->exists)
        <form method="POST" action="{{ route('pages.destroy', $page) }}" onsubmit="return confirm('Delete this page? Child pages move to the top level.')" class="mt-8">
            @csrf @method('DELETE')<button class="text-sm text-red-400 underline">Delete page</button>
        </form>
    @endif
</x-layouts.app>
