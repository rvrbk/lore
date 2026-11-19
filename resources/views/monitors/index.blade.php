<x-layouts.app title="Server health">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Server health</h1>
        @if($monitors->isNotEmpty())
            <form method="POST" action="{{ route('monitors.check') }}">@csrf<button class="rounded border px-3 py-1.5 text-sm hover:bg-zinc-800">Check all now</button></form>
        @endif
    </div>
    <p class="mt-2 text-sm text-zinc-400">Each app's URL is requested every minute while the scheduler runs. A response below HTTP 500 counts as up; timeouts, connection errors and 5xx count as down.</p>

    <details class="mt-5 border border-zinc-800 bg-zinc-900 p-3" @if($errors->any()) open @endif>
        <summary class="cursor-pointer text-sm font-medium">Add an app</summary>
        <form method="POST" action="{{ route('monitors.store') }}" class="mt-3 grid gap-3 sm:grid-cols-[1fr_2fr_auto]">
            @csrf
            <input name="name" value="{{ old('name') }}" placeholder="Name" required maxlength="100" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
            <input name="url" value="{{ old('url') }}" placeholder="https://example.com/up" required class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
            <button class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Add</button>
            @if($errors->any())<p class="text-sm text-red-400 sm:col-span-3">{{ $errors->first() }}</p>@endif
        </form>
    </details>

    @if($monitors->isNotEmpty())
        <ul class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">@foreach($monitors as $monitor) @include('partials.monitor-tile', ['monitor' => $monitor]) @endforeach</ul>

        <h2 class="mt-8 mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-400">Edit apps</h2>
        <ul class="divide-y divide-zinc-800 border border-zinc-800 bg-zinc-900">
            @foreach($monitors as $monitor)
                <li class="flex flex-wrap items-center gap-2 p-3">
                    <form method="POST" action="{{ route('monitors.update', $monitor) }}" class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                        @csrf @method('PUT')
                        <input name="name" value="{{ $monitor->name }}" required maxlength="100" aria-label="Name" class="w-40 rounded-md border border-zinc-700 px-2 py-1 text-sm">
                        <input name="url" value="{{ $monitor->url }}" required aria-label="URL" class="min-w-48 flex-1 rounded-md border border-zinc-700 px-2 py-1 text-sm">
                        <button class="rounded border px-2 py-1 text-xs hover:bg-zinc-800">Save</button>
                    </form>
                    <form method="POST" action="{{ route('monitors.check-one', $monitor) }}">@csrf<button class="rounded border px-2 py-1 text-xs hover:bg-zinc-800">Check</button></form>
                    <form method="POST" action="{{ route('monitors.destroy', $monitor) }}" onsubmit="return confirm('Stop monitoring {{ e($monitor->name) }}?')">@csrf @method('DELETE')<button class="rounded border px-2 py-1 text-xs text-red-400 hover:bg-red-500/10">Delete</button></form>
                </li>
            @endforeach
        </ul>
    @else
        <p class="mt-6 text-sm text-zinc-400">No apps yet. Add one above, for example the health or home URL of each app on the server.</p>
    @endif
</x-layouts.app>
