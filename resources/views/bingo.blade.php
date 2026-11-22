<x-layouts.app title="Visitor bingo">
    <h1 class="text-2xl font-semibold">Visitor bingo 🎯</h1>
    <p class="mt-2 text-sm text-zinc-400">Week {{ $card['week'] }}. A square lights up when one of your sites had a visitor from that country in the last 7 days. {{ $card['lit'] }} of 24 lit · {{ $card['lines'] }} {{ \Illuminate\Support\Str::plural('line', $card['lines']) }}.</p>
    @unless($hasData)<p class="mt-3 text-sm text-amber-300">No visitor data yet, so nothing can light up.</p>@endunless
    @if($card['lines'] > 0)<p class="mt-3 font-pixel text-lg text-emerald-400">BINGO!</p>@endif

    <div class="mt-6 grid max-w-xl grid-cols-5 gap-1.5">
        @foreach($card['rows'] as $row)
            @foreach($row as $cell)
                <div class="flex aspect-square flex-col items-center justify-center border p-1 text-center text-[11px] leading-tight {{ $cell['free'] ? 'border-indigo-500 bg-indigo-500/20 text-indigo-300' : ($cell['lit'] ? 'border-emerald-500 bg-emerald-600/30 text-emerald-300' : 'border-zinc-800 bg-zinc-900 text-zinc-400') }}">
                    <span class="font-semibold">{{ $cell['free'] ? '★' : $cell['code'] }}</span>
                    <span class="mt-0.5 break-words">{{ $cell['free'] ? 'Free' : $cell['name'] }}</span>
                </div>
            @endforeach
        @endforeach
    </div>
</x-layouts.app>
