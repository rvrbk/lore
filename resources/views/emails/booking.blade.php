<!DOCTYPE html>
<html lang="en">
<body style="font-family: -apple-system, Segoe UI, sans-serif; color: #18181b; line-height: 1.5;">
@php($cancelled = $event === 'cancelled')
<h2 style="margin: 0 0 12px;">{{ $cancelled ? 'Meeting cancelled' : 'Meeting confirmed' }}</h2>
@if($forOwner)
    <p>{{ $booking->guest_name }} ({{ $booking->guest_email }}) {{ $cancelled ? 'cancelled' : 'booked' }} <strong>{{ $booking->type->name }}</strong>.</p>
@else
    <p>Hi {{ $booking->guest_name }}, your <strong>{{ $booking->type->name }}</strong> {{ $cancelled ? 'has been cancelled' : 'is confirmed' }}.</p>
@endif
<p style="font-size: 16px;"><strong>{{ $when }}</strong></p>
@if($booking->notes)<p style="color: #52525b;">Notes: {{ $booking->notes }}</p>@endif
@if(! $cancelled && ! $forOwner)
    <p>Need to change plans? <a href="{{ route('pointy.booking', $booking->token) }}">Cancel or view your booking</a>.</p>
@endif
<p style="color: #71717a; font-size: 12px;">Sent by Pointy</p>
</body>
</html>
