@php
    $body = ['..X....X..', '..XX..XX..', '.XXXXXXXX.', 'XXXXXXXXXX', 'XXXXXXXXXX', 'XXXXXXXXXX', '.XXXXXXXX.', '.XX....XX.'];
    $sick = $monitor->is_up === false || in_array($monitor->id, $openMonitorIds ?? [], true);
    $egg = $monitor->is_up === null;
    $days = $monitor->is_up ? (int) ($monitor->status_changed_at?->diffInDays(now()) ?? 0) : 0;
    $scale = $days >= 30 ? 'size-9' : ($days >= 7 ? 'size-8' : ($days >= 1 ? 'size-7' : 'size-6'));
    $hue = crc32($monitor->name) % 360;
    $state = $egg ? 'pending' : ($sick ? 'sick' : 'happy');
@endphp
<svg viewBox="0 0 10 10" class="pet shrink-0 {{ $scale }}" data-pet="{{ $state }}" style="color: {{ $sick ? '#7aa36b' : 'hsl('.$hue.' 65% 68%)' }}" role="img" aria-label="{{ $monitor->name }} is {{ $egg ? 'not checked yet' : ($sick ? 'unwell' : 'happy') }}{{ $days >= 30 ? ' and has been up for a month' : '' }}" shape-rendering="crispEdges">
    <title>{{ $egg ? 'Waiting to hatch' : ($sick ? 'Not feeling well' : 'Happy'.($days ? ', up '.$days.' '.\Illuminate\Support\Str::plural('day', $days) : '')) }}</title>
    @if($egg)
        <ellipse cx="5" cy="6" rx="3" ry="3.6" fill="currentColor"/><path d="M3 5l1 1 1-1 1 1 1-1" stroke="#0f0b23" stroke-width=".4" fill="none"/>
    @else
        @foreach($body as $y => $row)@foreach(str_split($row) as $x => $cell)@if($cell === 'X')<rect x="{{ $x }}" y="{{ $y + 1 }}" width="1.02" height="1.02" fill="currentColor"/>@endif @endforeach @endforeach
        @if($days >= 30)<path d="M3 1h1v1h1V1h1v1h1V1h1v1H3z" fill="#ffe600"/>@endif
        @if($sick)
            <path d="M3 4l1 1m0-1l-1 1M6 4l1 1m0-1l-1 1" stroke="#0f0b23" stroke-width=".5"/><rect x="4" y="6" width="2" height=".6" fill="#0f0b23"/>
        @else
            <g class="pet-awake"><rect x="3" y="4" width="1" height="1.2" fill="#0f0b23"/><rect x="6" y="4" width="1" height="1.2" fill="#0f0b23"/><path d="M3.5 6.3l.8.7h1.4l.8-.7" stroke="#0f0b23" stroke-width=".5" fill="none"/></g>
            <g class="pet-asleep"><rect x="3" y="4.6" width="1.2" height=".5" fill="#0f0b23"/><rect x="5.8" y="4.6" width="1.2" height=".5" fill="#0f0b23"/><text x="7.2" y="3.4" font-size="2.2" fill="currentColor" font-family="monospace">z</text></g>
        @endif
    @endif
</svg>
