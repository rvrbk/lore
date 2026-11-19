<x-layouts.app :title="$page->title" :space="$space" :page="$page">
    <div>
        <article class="min-w-0">
            <div class="text-xs text-zinc-400">
                <a href="{{ route('spaces.show', $space) }}" class="hover:underline">{{ $space->name }}</a>
                @foreach($page->breadcrumbs() as $crumb) / <a href="{{ route('pages.show', $crumb) }}" class="hover:underline">{{ $crumb->title }}</a>@endforeach
            </div>
            <div data-inline-editor data-open="{{ $errors->any() ? 'true' : 'false' }}">
                <div class="mt-1 flex items-start justify-between gap-4">
                    <h1 data-view data-click-to-edit="title" title="Click to edit" class="min-w-0 flex-1 cursor-text text-3xl font-semibold hover:bg-zinc-800/60">{{ $page->title }}</h1>
                    <div class="flex shrink-0 gap-2 text-sm">
                        <a href="{{ route('pages.create', [$space, 'parent' => $page->id]) }}" class="rounded border px-3 py-1.5 hover:bg-zinc-800">Add child</a>
                        <a href="{{ route('pages.history', $page) }}" class="rounded border px-3 py-1.5 hover:bg-zinc-800">History</a>
                        <button type="button" data-edit-trigger class="rounded bg-indigo-600 px-3 py-1.5 font-medium text-white hover:bg-indigo-700">Edit</button>
                    </div>
                </div>
                <div class="mt-1 text-xs text-zinc-400">Updated {{ $page->updated_at->diffForHumans() }}</div>

                <div data-view data-click-to-edit="body" title="Click to edit" class="md mt-4 cursor-text rounded-lg border border-zinc-800 bg-zinc-900 p-6 transition hover:border-zinc-600">{!! $page->html() ?: '<p class="text-zinc-500">This page is empty. Click to start writing.</p>' !!}</div>

                <form data-edit hidden method="POST" action="{{ route('pages.update', $page) }}" class="mt-4 space-y-3">
                    @csrf @method('PUT')
                    <input type="hidden" name="parent_id" value="{{ $page->parent_id }}">
                    <input name="title" value="{{ old('title', $page->title) }}" placeholder="Title" required class="w-full rounded-md border border-zinc-700 px-3 py-2 text-2xl font-semibold">
                    <textarea name="body" rows="18" placeholder="Write in Markdown…" class="w-full rounded-md border border-zinc-700 px-3 py-2 font-mono text-sm">{{ old('body', $page->body) }}</textarea>
                    @if($errors->any())<p class="text-sm text-red-400">{{ $errors->first() }}</p>@endif
                    <div class="flex items-center gap-3">
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button>
                        <button type="button" data-edit-cancel class="px-4 py-2 text-sm text-zinc-400 hover:text-zinc-100">Cancel</button>
                        <span class="ml-auto text-xs text-zinc-500">Ctrl+S to save · Esc to cancel · <a href="{{ route('pages.edit', $page) }}" class="underline">full editor</a></span>
                    </div>
                </form>
            </div>

            <section class="mt-8">
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-400">Reminders for this page</h2>
                <ul class="space-y-2">@foreach($reminders as $reminder) @include('partials.reminder-row', ['reminder' => $reminder]) @endforeach</ul>
                <details class="mt-3 rounded-lg border border-zinc-800 bg-zinc-900 p-3">
                    <summary class="cursor-pointer text-sm font-medium">Add a reminder</summary>
                    <div class="mt-3">@include('partials.reminder-form', ['pageId' => $page->id])</div>
                </details>
            </section>
        </article>
    </div>
</x-layouts.app>
