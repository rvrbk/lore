@if($headerClock)
    <button type="button" data-header-clock="{{ $headerClock->timezone }}" title="{{ $headerClock->city }} · tap to switch 12/24 hour" class="{{ $class }} items-center gap-2 border border-zinc-800 bg-zinc-900 px-2 py-1 text-xs {{ str_contains($class, 'flex') ? '' : 'flex' }}">
        <span class="text-zinc-400">{{ \Illuminate\Support\Str::before($headerClock->city, ' (') }}</span>
        <span class="font-semibold tabular-nums text-indigo-400" data-header-clock-time>{{ now($headerClock->timezone)->format('H:i') }}</span>
    </button>
@endif
