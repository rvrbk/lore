<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => 'Your booking · Pointy'])
    <meta name="robots" content="noindex">
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-200 antialiased">
@php($start = $booking->starts_at->copy()->setTimezone($booking->guest_timezone))
<main class="mx-auto max-w-xl p-4 sm:p-8">
    <div class="font-pixel text-sm text-zinc-500">Pointy<span class="text-indigo-400">_</span></div>
    <h1 class="mt-4 text-2xl font-semibold text-zinc-100">{{ $booking->isCancelled() ? 'Booking cancelled' : 'You are booked' }}</h1>
    <div class="mt-5 border border-zinc-800 bg-zinc-900 p-4">
        <div class="font-medium text-zinc-100">{{ $booking->type->name }}</div>
        <div class="mt-2 text-lg {{ $booking->isCancelled() ? 'text-zinc-500 line-through' : 'text-indigo-400' }}">{{ $start->format('l j F Y') }}, <span data-local-time="{{ $booking->starts_at->copy()->utc()->toIso8601String() }}" data-zone="{{ $booking->guest_timezone }}">{{ $start->format('H:i') }}</span> – <span data-local-time="{{ $booking->ends_at->copy()->utc()->toIso8601String() }}" data-zone="{{ $booking->guest_timezone }}">{{ $booking->ends_at->copy()->setTimezone($booking->guest_timezone)->format('H:i') }}</span></div>
        <div class="text-sm text-zinc-400">{{ str_replace('_', ' ', $booking->guest_timezone) }}</div>
    </div>
    @if($booking->meeting_url && ! $booking->isCancelled())
        <a href="{{ $booking->meeting_url }}" target="_blank" rel="noopener" class="mt-4 inline-block rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Join with Google Meet</a>
        <div class="mt-1 text-xs text-zinc-500">{{ $booking->meeting_url }}</div>
    @endif
    @unless($booking->isCancelled())
        <p class="mt-3 text-sm text-zinc-400">A confirmation with a calendar invite was sent to {{ $booking->guest_email }}.</p>
        @if($booking->starts_at->isFuture())
            <form method="POST" action="{{ route('pointy.cancel', $booking->token) }}" class="mt-5" onsubmit="return confirm('Cancel this booking?')">@csrf<button class="rounded border border-red-500/50 px-3 py-1.5 text-sm text-red-400 hover:bg-zinc-800">Cancel booking</button></form>
        @endif
    @endunless
    <a href="{{ route('pointy.show', $booking->type->slug) }}" class="mt-5 inline-block text-sm text-indigo-400 hover:underline">Book another time</a>
</main>
</body>
</html>
