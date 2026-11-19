@php
    $checks = $checks->values();
    $width = $width ?? 120;
    $height = $height ?? 24;
    $count = $checks->count();
    $max = max(1, (int) $checks->where('is_up', true)->max('response_ms'));
    $step = $count > 1 ? $width / ($count - 1) : 0;
    $points = $checks->map(fn ($c, $i) => $c->is_up ? round($i * $step, 1).','.round($height - 2 - ($c->response_ms / $max) * ($height - 4), 1) : null)->filter()->implode(' ');
@endphp
@if($count > 1)
    <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" class="{{ $class ?? 'h-6 w-full' }} text-indigo-400" role="img" aria-label="Response time over the recent checks; red marks mean the app was down">
        @foreach($checks as $i => $c)@unless($c->is_up)<rect x="{{ round($i * $step - 1, 1) }}" y="0" width="2" height="{{ $height }}" fill="#ff8a9a"/>@endunless @endforeach
        @if($points !== '')<polyline fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" points="{{ $points }}"/>@endif
    </svg>
@endif
