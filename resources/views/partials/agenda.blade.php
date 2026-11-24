<section class="mt-12">
    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-400">Next {{ count($agenda) }} days</h2>
    <div class="grid gap-1.5 sm:grid-cols-3 xl:grid-cols-6">
        @foreach($agenda as $day)
            <div class="border border-zinc-800 bg-zinc-900 p-2">
                <div class="mb-1.5 text-xs font-semibold {{ $loop->first ? 'text-indigo-400' : 'text-zinc-300' }}">{{ $loop->first ? 'Today' : ($loop->index === 1 ? 'Tomorrow' : $day['date']->format('l')) }} <span class="font-normal text-zinc-500">{{ $day['date']->format('j M') }}</span></div>
                @forelse($day['items'] as $item)
                    <div class="mb-1 flex gap-1.5 border-l-2 pl-1.5 text-xs leading-snug" style="border-color: {{ $item['color'] }}" title="{{ $item['kind'] }}">
                        <span class="w-9 shrink-0 tabular-nums text-zinc-500">{{ $item['all_day'] ? 'all day' : $item['start']->setTimezone(config('app.timezone'))->format('H:i') }}</span>
                        <span class="min-w-0 break-words text-zinc-200">{{ $item['title'] }}@if($item['location'])<span class="block truncate text-zinc-500">{{ $item['location'] }}</span>@endif</span>
                    </div>
                @empty
                    <div class="text-xs text-zinc-600">Nothing planned.</div>
                @endforelse
            </div>
        @endforeach
    </div>
    <details class="mt-3 text-sm">
        <summary class="cursor-pointer text-zinc-400 hover:text-zinc-100">Calendar feeds ({{ $calendarSources->count() }})</summary>
        @if($calendarSources->isNotEmpty())
            <ul class="mt-2 space-y-1">
                @foreach($calendarSources as $source)
                    <li class="flex items-center gap-2"><span class="size-2.5 rounded-full" style="background: {{ $source->color }}"></span>{{ $source->name }}
                        <form method="POST" action="{{ route('calendar-sources.destroy', $source) }}" onsubmit="return confirm('Remove {{ e($source->name) }}?')">@csrf @method('DELETE')<button class="text-xs text-zinc-500 hover:text-red-400">Remove</button></form>
                    </li>
                @endforeach
            </ul>
        @endif
        <form method="POST" action="{{ route('calendar-sources.store') }}" class="mt-2 flex flex-wrap items-center gap-2">
            @csrf
            <input name="name" placeholder="Name" required maxlength="60" class="w-32 rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
            <input name="url" placeholder="ICS address (Google secret iCal link or iCloud public calendar)" required class="w-96 max-w-full rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
            <input type="color" name="color" value="#818cf8" class="h-8 w-10 rounded border border-zinc-700 bg-transparent" aria-label="Color">
            <button class="rounded border px-3 py-1.5 text-sm hover:bg-zinc-800">Add feed</button>
            @error('name')<span class="text-sm text-red-400">{{ $message }}</span>@enderror
            @error('url')<span class="text-sm text-red-400">{{ $message }}</span>@enderror
        </form>
    </details>
</section>
