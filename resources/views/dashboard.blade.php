<x-layouts.app title="Home">
    <div class="flex items-start justify-between gap-4">
        <h1 class="text-2xl font-semibold">Welcome back</h1>
        <a href="{{ route('export.all') }}" class="rounded border px-3 py-1.5 text-sm hover:bg-zinc-800" title="Download every page as Markdown files in a .zip">Export everything</a>
    </div>

    @if($jobScout)
        <a href="{{ $jobScout['url'] }}" target="_blank" rel="noopener" class="mt-6 flex items-center justify-between border border-zinc-800 bg-zinc-900 p-3 hover:border-zinc-500">
            <span class="text-sm text-zinc-400">New vacancies in Job Scout</span>
            <span class="text-2xl font-semibold {{ $jobScout['new'] ? 'text-indigo-400' : 'text-zinc-500' }}">{{ $jobScout['new'] }}</span>
        </a>
    @endif

    <section class="mt-12">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-400">World clocks</h2>
        <ul class="grid grid-cols-2 gap-1.5 sm:grid-cols-4 xl:grid-cols-8" data-clock-list data-reorder-url="{{ route('world-clocks.reorder') }}">
            @foreach($clocks as $clock)
                <li class="group relative flex cursor-grab touch-manipulation select-none items-center justify-between gap-1 border border-zinc-800 bg-zinc-900 px-2 py-1" draggable="true" data-clock-id="{{ $clock->id }}" data-world-clock="{{ $clock->timezone }}" data-analog="{{ $clock->is_analog ? 1 : 0 }}">
                    <div class="min-w-0">
                        <div class="truncate text-[11px] font-medium leading-tight text-zinc-100">{{ $clock->city }}</div>
                        <div class="text-[10px] leading-tight text-zinc-500" data-clock-date>{{ now($clock->timezone)->format('D j M') }}</div>
                    </div>
                    @php($local = now($clock->timezone))
                    <svg viewBox="0 0 48 32" class="h-8 w-12 shrink-0 text-indigo-400 {{ $clock->is_analog ? '' : 'hidden' }}" data-clock-face aria-hidden="true">
                        <rect x="1" y="1" width="46" height="30" rx="3" fill="none" stroke="currentColor" stroke-opacity=".5" stroke-width="1.5"/>
                        <path d="M24 2.5V5M24 27V29.5M2.5 16H5M43 16H45.5" stroke="currentColor" stroke-opacity=".6" stroke-width="1.2"/>
                        <line x1="24" y1="16" x2="24" y2="9" stroke="currentColor" stroke-width="2" stroke-linecap="round" data-hand="hour" transform="rotate({{ ($local->hour % 12) * 30 + $local->minute * 0.5 }} 24 16)"/>
                        <line x1="24" y1="16" x2="24" y2="5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" data-hand="minute" transform="rotate({{ $local->minute * 6 }} 24 16)"/>
                        <line x1="24" y1="18" x2="24" y2="4.5" stroke="#a1a1aa" stroke-width=".8" stroke-linecap="round" data-hand="second" transform="rotate({{ $local->second * 6 }} 24 16)"/>
                    </svg>
                    <button type="button" class="{{ $clock->is_analog ? 'hidden' : '' }} shrink-0 text-sm font-semibold tabular-nums text-indigo-400" data-clock-time title="Tap to switch 12/24 hour">{{ now($clock->timezone)->format('H:i') }}<sup class="ml-px text-[9px] font-medium text-indigo-300/70">{{ now($clock->timezone)->format('s') }}</sup></button>
                    <form method="POST" action="{{ route('world-clocks.style', $clock) }}" class="absolute -left-1 -top-1 hidden group-hover:block [@media(hover:none)]:block">@csrf @method('PATCH')<button class="rounded-full bg-zinc-800 px-1 text-[9px] text-zinc-400 hover:text-indigo-400" title="Switch between analog and digital" aria-label="Switch {{ $clock->city }} between analog and digital">◷</button></form>
                    <form method="POST" action="{{ route('world-clocks.header', $clock) }}" class="absolute -bottom-1 -left-1 {{ $clock->is_header ? '' : 'hidden group-hover:block [@media(hover:none)]:block' }}">@csrf @method('PATCH')<button class="rounded-full bg-zinc-800 px-1 text-[9px] {{ $clock->is_header ? 'text-indigo-400' : 'text-zinc-400 hover:text-indigo-400' }}" title="{{ $clock->is_header ? 'Remove from the header' : 'Show in the header' }}" aria-label="{{ $clock->is_header ? 'Remove '.$clock->city.' from the header' : 'Show '.$clock->city.' in the header' }}">{{ $clock->is_header ? '★' : '☆' }}</button></form>
                    <form method="POST" action="{{ route('world-clocks.destroy', $clock) }}" onsubmit="return confirm('Remove {{ e($clock->city) }}?')" class="absolute -right-1 -top-1 hidden group-hover:block [@media(hover:none)]:block">@csrf @method('DELETE')<button class="rounded-full bg-zinc-800 px-1 text-[9px] text-zinc-400 hover:text-red-400" title="Remove clock" aria-label="Remove {{ $clock->city }}">✕</button></form>
                </li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('world-clocks.store') }}" class="mt-3 flex flex-wrap items-center gap-2">
            @csrf
            <input name="city" list="world-clock-cities" placeholder="Add a city… (type to search)" autocomplete="off" required class="w-64 max-w-full rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
            <datalist id="world-clock-cities">@foreach($cities as $label)<option value="{{ str_replace(',', '', $label) }}"></option>@endforeach</datalist>
            <button class="rounded border px-3 py-1.5 text-sm hover:bg-zinc-800">Add clock</button>
            @error('city')<span class="text-sm text-red-400">{{ $message }}</span>@enderror
        </form>
    </section>

    <section class="mt-12">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Server health
                @if($monitors->isNotEmpty())
                    <span class="ml-2 normal-case {{ $monitors->contains('is_up', false) ? 'text-red-400' : 'text-emerald-400' }}">{{ $monitors->where('is_up', true)->count() }}/{{ $monitors->count() }} up</span>
                @endif
            </h2>
            <div class="flex items-center gap-3 text-sm">
                @if($monitors->isNotEmpty())
                    <form method="POST" action="{{ route('monitors.check') }}">@csrf<button class="text-zinc-400 hover:text-zinc-100">Check now</button></form>
                @endif
                <a href="{{ route('monitors.index') }}" class="text-zinc-400 hover:text-zinc-100">Manage</a>
            </div>
        </div>
        @if($monitors->isEmpty())
            <p class="text-sm text-zinc-400">No apps monitored yet. <a href="{{ route('monitors.index') }}" class="underline">Add the first one</a>.</p>
        @else
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">@foreach($monitors as $monitor) @include('partials.monitor-tile', ['monitor' => $monitor, 'sparks' => $sparks]) @endforeach</ul>
        @endif
    </section>

    @if($analytics)
        <section class="mt-12">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Analytics <span class="ml-2 normal-case">last 7 days · daily users</span></h2>
                <div class="flex items-center gap-3 text-sm">
                    <a href="{{ route('analytics.index') }}" class="text-zinc-400 hover:text-zinc-100">Manage</a>
                </div>
            </div>
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($analytics as $site)
                    <li class="border border-zinc-800 bg-zinc-900 p-3">@include('partials.analytics-tile', ['site' => $site])</li>
                @endforeach
            </ul>
            <div class="mt-4">@include('partials.world-map')</div>
        </section>
    @endif

    <section class="mt-12">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-400">Due now</h2>
        @if($due->isEmpty())
            <p class="text-sm text-zinc-400">Nothing due. 🎉</p>
        @else
            <ul class="space-y-2">@foreach($due as $reminder) @include('partials.reminder-row', ['reminder' => $reminder]) @endforeach</ul>
        @endif
    </section>

    <section class="mt-12">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-400">Next 14 days</h2>
        @if($upcoming->isEmpty())
            <p class="text-sm text-zinc-400">No upcoming reminders. <a href="{{ route('reminders.index') }}" class="underline">Add one</a>.</p>
        @else
            <ul class="space-y-2">@foreach($upcoming as $reminder) @include('partials.reminder-row', ['reminder' => $reminder]) @endforeach</ul>
        @endif
    </section>

    <section class="mt-12">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-400">Recently updated pages</h2>
        <ul class="divide-y divide-zinc-800 rounded-lg border border-zinc-800 bg-zinc-900 text-sm">
            @forelse($recent as $p)
                <li class="flex justify-between px-3 py-2"><a href="{{ route('pages.show', $p) }}" class="font-medium hover:underline">{{ $p->title }}</a><span class="text-zinc-400">{{ $p->space->name }} · {{ $p->updated_at->diffForHumans() }}</span></li>
            @empty
                <li class="px-3 py-2 text-zinc-400">No pages yet. Create a space to get started.</li>
            @endforelse
        </ul>
    </section>
</x-layouts.app>
