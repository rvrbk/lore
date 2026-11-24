@if($nextBooking)
    @php
        $zone = $nextBooking->type->timezone;
        $inProgress = $nextBooking->starts_at->isPast();
        $when = $inProgress ? 'now' : $nextBooking->starts_at->diffForHumans(['short' => true, 'parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]);
    @endphp
    <a href="{{ route('pointy.index') }}" title="Next booking: {{ $nextBooking->type->name }} with {{ $nextBooking->guest_name }}, {{ $nextBooking->starts_at->copy()->setTimezone($zone)->format('D j M, H:i') }} ({{ str_replace('_', ' ', $zone) }})" class="shrink-0 border border-zinc-800 bg-zinc-900 px-1.5 py-1 text-xs tabular-nums text-indigo-300 hover:bg-zinc-800">📅 <span class="max-sm:hidden">{{ $nextBooking->guest_name }} · </span>{{ $inProgress ? 'now' : 'in '.$when }}</a>
@endif
