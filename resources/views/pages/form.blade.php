<x-layouts.app :title="$page->exists ? 'Edit '.$page->title : 'New page'" :space="$space" :page="$page->exists ? $page : null">
    <h1 class="text-2xl font-semibold">{{ $page->exists ? 'Edit page' : 'New page in '.$space->name }}</h1>

    @unless($page->exists)
        <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
            <span class="text-zinc-400">Start from:</span>
            @foreach(['' => 'Blank'] + collect(\App\Models\Page::TEMPLATES)->map(fn ($t) => $t['label'])->all() as $key => $label)
                <a href="{{ route('pages.create', array_filter(['space' => $space, 'parent' => $page->parent_id, 'template' => $key])) }}" class="border px-2.5 py-1 hover:bg-zinc-800 {{ ($template ?? '') === (string) $key ? 'border-indigo-500 bg-indigo-500/15 text-zinc-100' : 'text-zinc-300' }}">{{ $label }}</a>
            @endforeach
        </div>
    @endunless

    <form method="POST" action="{{ $page->exists ? route('pages.update', $page) : route('pages.store', $space) }}" class="mt-6 space-y-4">
        @csrf
        @if($page->exists) @method('PUT') @endif
        @if(! $page->exists && $template)<input type="hidden" name="template" value="{{ $template }}">@endif
        <input name="title" value="{{ old('title', $page->title) }}" placeholder="Title" required class="w-full rounded-md border border-zinc-700 px-3 py-2 text-lg font-medium">
        <div class="grid gap-3 sm:grid-cols-2">
            <select name="parent_id" class="w-full rounded-md border border-zinc-700 px-3 py-2 text-sm">
                <option value="">Top level</option>
                @foreach($parents as $id => $label)
                    <option value="{{ $id }}" @selected(old('parent_id', $page->parent_id) == $id)>{{ $label }}</option>
                @endforeach
            </select>
            <input name="tags" value="{{ old('tags', $page->exists ? $page->tagList() : '') }}" placeholder="Tags, comma separated" maxlength="300" class="w-full rounded-md border border-zinc-700 px-3 py-2 text-sm">
        </div>
        @if(! $page->exists && ! empty($templateTasks))
            <label class="flex items-start gap-2 border border-zinc-800 bg-zinc-900 p-3 text-sm">
                <input type="checkbox" name="spawn_tasks" value="1" checked class="mt-0.5">
                <span>Also add these {{ count($templateTasks) }} tasks to the board, linked to this page and with due dates spread from today<span class="mt-1 block text-xs text-zinc-400">{{ collect($templateTasks)->pluck('title')->implode(' · ') }}</span></span>
            </label>
        @endif
        @include('partials.markdown-editor', ['value' => old('body', $page->body), 'rows' => 24])
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
