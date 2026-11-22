<x-layouts.app title="Pointy">
    <h1 class="text-2xl font-semibold">Pointy</h1>
    <p class="mt-2 text-sm text-zinc-400">Share a link, let people book a free slot in their own time zone. Confirmations go out from {{ config('services.pointy.from') }}.</p>

    @php($activeTypes = $types->where('is_active', true))
    @if($activeTypes->isNotEmpty())
        <section class="mt-5 border border-zinc-800 bg-zinc-900 p-3">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-400">Send a booking request</h2>
            @include('partials.pointy-request-form', ['bookingTypes' => $activeTypes])
        </section>
    @endif

    <details class="mt-5 border border-zinc-800 bg-zinc-900 p-3">
        <summary class="cursor-pointer text-sm font-medium">Add a meeting type</summary>
        <div class="mt-3">@include('partials.pointy-type-form', ['type' => null])</div>
    </details>

    <section class="mt-8">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-zinc-400">Sites</h2>
        <p class="mb-3 text-xs text-zinc-500">Each website that embeds Pointy gets its own CSS. Add <code>?site=key</code> to the booking link (or iframe URL) and the pages use that site's CSS on top of the default look, whichever meeting type is shown. Hooks: body.pointy, .pointy-main, .pointy-brand, .pointy-title, .pointy-meta, .pointy-zone, .pointy-days, .pointy-day, .pointy-day-active, .pointy-slots, .pointy-slot, .pointy-form, .pointy-input, .pointy-submit, .pointy-error, .pointy-card, .pointy-join, .pointy-cancel, .pointy-link.</p>
        @foreach($sites as $site)
            <div class="mb-3 border border-zinc-800 bg-zinc-900 p-3">
                <details>
                    <summary class="cursor-pointer text-sm"><span class="font-medium text-zinc-100">{{ $site->name }}</span> <span class="ml-2 text-xs text-zinc-500">?site={{ $site->key }}</span></summary>
                    <div class="mt-3">@include('partials.pointy-site-form', ['site' => $site])</div>
                    <form method="POST" action="{{ route('pointy.sites.destroy', $site) }}" class="mt-3" onsubmit="return confirm('Delete {{ e($site->name) }}?')">@csrf @method('DELETE')<button class="text-xs text-red-400 hover:underline">Delete this site</button></form>
                </details>
            </div>
        @endforeach
        <details class="border border-zinc-800 bg-zinc-900 p-3">
            <summary class="cursor-pointer text-sm font-medium">Add a site</summary>
            <div class="mt-3">@include('partials.pointy-site-form', ['site' => null])</div>
        </details>
    </section>

    <section class="mt-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-400">Meeting types</h2>
        @forelse($types as $type)
            <div class="mb-3 border border-zinc-800 bg-zinc-900 p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <span class="font-medium text-zinc-100">{{ $type->name }}</span>
                        <span class="ml-2 text-xs text-zinc-500">{{ $type->duration_minutes }} min · {{ $type->start_time }}–{{ $type->end_time }} {{ str_replace('_', ' ', $type->timezone) }}@unless($type->is_active) · paused @endunless</span>
                    </div>
                    <a href="{{ route('pointy.show', $type->slug) }}" target="_blank" rel="noopener" class="text-sm text-indigo-400 hover:underline">{{ route('pointy.show', $type->slug) }}</a>
                </div>
                <details class="mt-3 border-t border-zinc-800 pt-3">
                    <summary class="cursor-pointer text-xs text-zinc-400">Edit · {{ $type->bookings_count }} upcoming</summary>
                    <div class="mt-3">@include('partials.pointy-type-form', ['type' => $type])</div>
                    <div class="mt-4 border-t border-zinc-800 pt-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Exceptions for single days</h3>
                        <p class="mt-1 text-xs text-zinc-500">The weekly hours above are the general rule. Pick a day to close it, or fill in hours to use those instead (also works on a day that is normally off). Times are in {{ str_replace('_', ' ', $type->timezone) }}.</p>
                        @foreach($type->exceptions as $exception)
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                                <span class="text-zinc-200">{{ $exception->date->format('D j M Y') }} · {{ $exception->isClosed() ? 'closed' : $exception->start_time.'–'.$exception->end_time }}@if($exception->note) <span class="text-zinc-500">· {{ $exception->note }}</span>@endif</span>
                                <form method="POST" action="{{ route('pointy.exceptions.destroy', $exception) }}">@csrf @method('DELETE')<button class="text-xs text-red-400 hover:underline">Remove</button></form>
                            </div>
                        @endforeach
                        <form method="POST" action="{{ route('pointy.exceptions.store', $type) }}" class="mt-3 flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="date" name="date" required aria-label="Date" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
                            <input type="time" name="start_time" aria-label="From" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
                            <input type="time" name="end_time" aria-label="Until" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
                            <input name="note" maxlength="255" placeholder="Note (optional)" aria-label="Note" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
                            <button class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Add exception</button>
                        </form>
                    </div>
                    <form method="POST" action="{{ route('pointy.types.destroy', $type) }}" class="mt-3" onsubmit="return confirm('Delete {{ e($type->name) }} and all its bookings?')">@csrf @method('DELETE')<button class="text-xs text-red-400 hover:underline">Delete this meeting type</button></form>
                </details>
            </div>
        @empty
            <p class="text-sm text-zinc-400">No meeting types yet. Add the first one above.</p>
        @endforelse
    </section>

    <section class="mt-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-400">Upcoming bookings</h2>
        @forelse($bookings as $booking)
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2 border border-zinc-800 bg-zinc-900 p-3 text-sm">
                <div>
                    <div class="font-medium text-zinc-100">{{ $booking->guest_name }} <span class="font-normal text-zinc-500">{{ $booking->guest_email }}</span></div>
                    <div class="text-zinc-300">{{ $booking->type->name }} · {{ $booking->starts_at->copy()->setTimezone($booking->type->timezone)->format('D j M') }}, <span data-local-time="{{ $booking->starts_at->copy()->utc()->toIso8601String() }}" data-zone="{{ $booking->type->timezone }}">{{ $booking->starts_at->copy()->setTimezone($booking->type->timezone)->format('H:i') }}</span> <span class="text-zinc-500">your time</span> · {{ $booking->starts_at->copy()->setTimezone($booking->guest_timezone)->format('D j M') }}, <span data-local-time="{{ $booking->starts_at->copy()->utc()->toIso8601String() }}" data-zone="{{ $booking->guest_timezone }}">{{ $booking->starts_at->copy()->setTimezone($booking->guest_timezone)->format('H:i') }}</span> <span class="text-zinc-500">{{ str_replace('_', ' ', $booking->guest_timezone) }}</span></div>
                    @if($booking->meeting_url)<div class="mt-1 text-xs"><a href="{{ $booking->meeting_url }}" target="_blank" rel="noopener" class="text-indigo-400 hover:underline">Google Meet</a></div>@endif
                    @if($booking->notes)<div class="mt-1 text-xs text-zinc-400">{{ $booking->notes }}</div>@endif
                </div>
                <form method="POST" action="{{ route('pointy.bookings.cancel', $booking) }}" onsubmit="return confirm('Cancel this booking and email {{ e($booking->guest_name) }}?')">@csrf<button class="text-xs text-red-400 hover:underline">Cancel</button></form>
            </div>
        @empty
            <p class="text-sm text-zinc-400">No upcoming bookings.</p>
        @endforelse
    </section>
</x-layouts.app>
