<!DOCTYPE html>
<html lang="en">
<body style="font-family: -apple-system, Segoe UI, sans-serif; color: #18181b; line-height: 1.5;">
<h2 style="margin: 0 0 12px;">{{ $type->name }}</h2>
<p>{{ $guestName ? "Hi {$guestName}," : 'Hi,' }} {{ config('services.pointy.from_name') }} would like to meet with you{{ $type->duration_minutes ? " for {$type->duration_minutes} minutes" : '' }}.</p>
@if($type->description)<p style="color: #52525b;">{{ $type->description }}</p>@endif
@if($note)<p style="white-space: pre-line;">{{ $note }}</p>@endif
<p><a href="{{ $url }}" style="display: inline-block; padding: 8px 16px; background: #4f46e5; color: #fff; border-radius: 4px; text-decoration: none;">Pick a time</a></p>
<p style="color: #71717a; font-size: 12px;">Sent by Pointy</p>
</body>
</html>
