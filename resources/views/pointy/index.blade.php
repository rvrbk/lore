<x-layouts.app title="Pointy">
    <h1 class="text-2xl font-semibold">Pointy</h1>
    <p class="mt-2 text-sm text-zinc-400">Share a link, let people book a free slot in their own time zone. Confirmations go out from {{ config('services.pointy.from') }}.</p>

    <details class="mt-5 border border-zinc-800 bg-zinc-900 p-3">
        <summary class="cursor-pointer text-sm font-medium">Add a meeting type</summary>
        <div class="mt-3">@include('partials.pointy-type-form', ['type' => null])</div>
    </details>

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
                    @if($booking->notes)<div class="mt-1 text-xs text-zinc-400">{{ $booking->notes }}</div>@endif
                </div>
                <form method="POST" action="{{ route('pointy.bookings.cancel', $booking) }}" onsubmit="return confirm('Cancel this booking and email {{ e($booking->guest_name) }}?')">@csrf<button class="text-xs text-red-400 hover:underline">Cancel</button></form>
            </div>
        @empty
            <p class="text-sm text-zinc-400">No upcoming bookings.</p>
        @endforelse
    </section>
</x-layouts.app>
