@php
    $width = 120;
    $height = 28;
    $values = array_values($values);
    $count = count($values);
    $max = max(1, max($values ?: [0]));
    $step = $count > 1 ? $width / ($count - 1) : 0;
    $points = collect($values)->map(fn ($v, $i) => round($i * $step, 1).','.round($height - 2 - ($v / $max) * ($height - 4), 1));
    $area = '0,'.$height.' '.$points->implode(' ').' '.$width.','.$height;
@endphp
@if($count > 1)
    <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" class="h-7 w-full text-indigo-400" role="img" aria-label="{{ $label }}: {{ implode(', ', $values) }}{{ ! empty($markers) ? '. Dashed lines mark deploys.' : '' }}">
        <polygon points="{{ $area }}" fill="currentColor" fill-opacity="0.15"/>
        <polyline fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" points="{{ $points->implode(' ') }}"/>
        @foreach($markers ?? [] as $marker)<line x1="{{ round($marker * $step, 1) }}" x2="{{ round($marker * $step, 1) }}" y1="0" y2="{{ $height }}" stroke="var(--color-red-400)" stroke-width="1" stroke-dasharray="2 2" vector-effect="non-scaling-stroke"><title>Deploy</title></line>@endforeach
    </svg>
@endif
