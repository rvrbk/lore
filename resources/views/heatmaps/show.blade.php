<x-layouts.app :title="'Clicks · '.$monitor->name">
    <a href="{{ route('analytics.index') }}#clicks" class="text-sm text-zinc-400 hover:underline">← Analytics</a>
    <h1 class="mt-1 text-2xl font-semibold">{{ $monitor->name }}</h1>

    <form method="GET" class="mt-4 flex flex-wrap items-center gap-2 text-sm">
        <select name="path" onchange="this.form.submit()" aria-label="Page" class="max-w-full rounded-md border border-zinc-700 px-2 py-1.5">
            @forelse($paths as $candidate => $clicks)
                <option value="{{ $candidate }}" @selected($candidate === $path)>{{ $candidate }} ({{ number_format($clicks) }})</option>
            @empty
                <option>No pages yet</option>
            @endforelse
        </select>
        <select name="device" onchange="this.form.submit()" aria-label="Device" class="rounded-md border border-zinc-700 px-2 py-1.5">
            @foreach(['desktop' => 'Desktop', 'mobile' => 'Mobile'] as $value => $label)<option value="{{ $value }}" @selected($value === $device)>{{ $label }}</option>@endforeach
        </select>
        <select name="days" onchange="this.form.submit()" aria-label="Period" class="rounded-md border border-zinc-700 px-2 py-1.5">
            @foreach($dayChoices as $choice)<option value="{{ $choice }}" @selected($choice === $days)>Last {{ $choice }} days</option>@endforeach
        </select>
        @if($path !== '')<span class="text-xs text-zinc-500">{{ number_format($points->count()) }} clicks shown</span>@endif
    </form>
    @if(session('status'))<p class="mt-2 text-sm text-emerald-400">{{ session('status') }}</p>@endif
    @if(session('error'))<p class="mt-2 text-sm text-amber-300">{{ session('error') }}</p>@endif

    @php
        $maxDaily = max(1, (int) $daily->max('clicks'));
        $totalClicks = (int) $daily->sum('clicks');
        $maxPage = max(1, (int) $topPages->max());
        $deviceTotal = max(1, (int) $devices->sum());
    @endphp
    <section class="mt-6 grid gap-3 lg:grid-cols-3">
        <div class="border border-zinc-800 bg-zinc-900 p-3 lg:col-span-2">
            <div class="flex items-baseline justify-between text-xs uppercase text-zinc-400"><span>Clicks per day</span><span><span class="text-base text-zinc-100">{{ number_format($totalClicks) }}</span> in {{ $days }} days</span></div>
            <div class="mt-3 flex h-28 items-end gap-px" role="img" aria-label="Clicks per day over the last {{ $days }} days">
                @foreach($daily as $point)
                    <div class="flex-1 {{ $point['clicks'] ? 'bg-indigo-500' : 'bg-zinc-800' }}" style="height: {{ $point['clicks'] ? max(4, round($point['clicks'] / $maxDaily * 100)) : 2 }}%" title="{{ $point['day']->format('D j M') }}: {{ $point['clicks'] }} clicks"></div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-xs text-zinc-500"><span>{{ $daily->first()['day']->format('j M') }}</span><span>peak {{ $maxDaily }}/day</span><span>{{ $daily->last()['day']->format('j M') }}</span></div>
        </div>
        <div class="border border-zinc-800 bg-zinc-900 p-3">
            <div class="text-xs uppercase text-zinc-400">Device</div>
            @forelse(['desktop' => 'Desktop', 'mobile' => 'Mobile'] as $key => $label)
                @php $count = (int) $devices->get($key, 0); @endphp
                <div class="mt-3 text-sm"><div class="flex justify-between"><span>{{ $label }}</span><span class="text-zinc-400">{{ number_format($count) }} · {{ round($count / $deviceTotal * 100) }}%</span></div>
                    <div class="mt-1 h-2 bg-zinc-800"><div class="h-2 {{ $key === 'mobile' ? 'bg-amber-400' : 'bg-indigo-500' }}" style="width: {{ $count / $deviceTotal * 100 }}%"></div></div></div>
            @empty
            @endforelse
        </div>
        <div class="border border-zinc-800 bg-zinc-900 p-3 lg:col-span-3">
            <div class="text-xs uppercase text-zinc-400">Most clicked pages</div>
            @forelse($topPages as $page => $count)
                <a href="{{ route('analytics.heatmaps.show', ['monitor' => $monitor, 'path' => $page, 'device' => $device, 'days' => $days]) }}" class="mt-2 block text-sm hover:bg-zinc-800">
                    <div class="flex justify-between gap-3"><span class="truncate">{{ $page }}</span><span class="shrink-0 text-zinc-400">{{ number_format($count) }}</span></div>
                    <div class="mt-1 h-1.5 bg-zinc-800"><div class="h-1.5 bg-indigo-500" style="width: {{ $count / $maxPage * 100 }}%"></div></div>
                </a>
            @empty
                <p class="mt-2 text-sm text-zinc-400">No clicks in this period.</p>
            @endforelse
        </div>
    </section>

    @if($path === '')
        <p class="mt-6 text-sm text-zinc-400">No {{ $device }} clicks in the last {{ $days }} days. Once the tracker is live on {{ $monitor->host() }}, clicks appear here.</p>
    @else
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm" role="group" aria-label="View">
            <button type="button" data-heat-mode="heat" class="rounded-md border border-zinc-700 px-3 py-1 data-[active]:bg-indigo-600 data-[active]:text-white">Heat</button>
            <button type="button" data-heat-mode="dots" class="rounded-md border border-zinc-700 px-3 py-1 data-[active]:bg-indigo-600 data-[active]:text-white">Dots</button>
            <span class="text-xs text-zinc-500">Heat blurs busy areas red; Dots marks every single click. Left to right is the page width, top to bottom is how far down the page (px).</span>
        </div>
        <div class="mt-3 overflow-x-auto border border-zinc-800">
            <div id="heat-stage" class="relative bg-zinc-900" style="width: {{ $width }}px">
                <canvas id="heat-canvas" class="block"></canvas>
            </div>
        </div>
        <script>
            (function () {
                var points = @json($points);
                var width = {{ $width }};
                var canvas = document.getElementById('heat-canvas');
                var ctx = canvas.getContext('2d');
                var maxY = points.reduce(function (max, p) { return Math.max(max, p[1]); }, 0);
                var height = Math.min(15000, Math.max(800, maxY + 300));
                canvas.width = width;
                canvas.height = height;

                var radius = width > 600 ? 28 : 18;
                var palette = (function () {
                    var ramp = document.createElement('canvas');
                    ramp.width = 1;
                    ramp.height = 256;
                    var rctx = ramp.getContext('2d');
                    var colors = rctx.createLinearGradient(0, 0, 0, 256);
                    [[0.0, 'rgba(0,0,255,0)'], [0.25, 'rgb(0,0,255)'], [0.5, 'rgb(0,255,0)'], [0.75, 'rgb(255,255,0)'], [1.0, 'rgb(255,0,0)']].forEach(function (s) { colors.addColorStop(s[0], s[1]); });
                    rctx.fillStyle = colors;
                    rctx.fillRect(0, 0, 1, 256);
                    return rctx.getImageData(0, 0, 1, 256).data;
                })();

                function drawGrid() {
                    ctx.font = '11px monospace';
                    ctx.textBaseline = 'top';
                    for (var y = 0; y < height; y += 250) {
                        ctx.fillStyle = y % 500 === 0 ? 'rgba(161,161,170,0.35)' : 'rgba(161,161,170,0.15)';
                        ctx.fillRect(0, y, width, 1);
                        if (y % 500 === 0) {
                            ctx.fillStyle = 'rgba(161,161,170,0.8)';
                            ctx.fillText(y + ' px', 6, y + 4);
                        }
                    }
                    for (var i = 1; i < 4; i++) {
                        ctx.fillStyle = 'rgba(161,161,170,0.15)';
                        ctx.fillRect(Math.round(width * i / 4), 0, 1, height);
                    }
                }

                function drawHeat() {
                    var shadow = document.createElement('canvas');
                    shadow.width = width;
                    shadow.height = height;
                    var sctx = shadow.getContext('2d');
                    points.forEach(function (p) {
                        var x = p[0] / 1000 * width, y = p[1];
                        var gradient = sctx.createRadialGradient(x, y, 0, x, y, radius);
                        gradient.addColorStop(0, 'rgba(0,0,0,0.12)');
                        gradient.addColorStop(1, 'rgba(0,0,0,0)');
                        sctx.fillStyle = gradient;
                        sctx.fillRect(x - radius, y - radius, radius * 2, radius * 2);
                    });
                    var image = sctx.getImageData(0, 0, width, height);
                    var data = image.data;
                    for (var i = 3; i < data.length; i += 4) {
                        var alpha = data[i];
                        if (alpha) {
                            var j = alpha * 4;
                            data[i - 3] = palette[j];
                            data[i - 2] = palette[j + 1];
                            data[i - 1] = palette[j + 2];
                            data[i] = Math.min(220, palette[j + 3] + 40);
                        }
                    }
                    sctx.putImageData(image, 0, 0);
                    ctx.drawImage(shadow, 0, 0);
                }

                function drawDots() {
                    points.forEach(function (p) {
                        var x = p[0] / 1000 * width, y = p[1];
                        ctx.beginPath();
                        ctx.arc(x, y, 9, 0, Math.PI * 2);
                        ctx.fillStyle = 'rgba(239,68,68,0.55)';
                        ctx.fill();
                        ctx.lineWidth = 2;
                        ctx.strokeStyle = 'rgba(255,255,255,0.95)';
                        ctx.stroke();
                    });
                }

                var buttons = document.querySelectorAll('[data-heat-mode]');
                function setMode(mode) {
                    ctx.clearRect(0, 0, width, height);
                    drawGrid();
                    (mode === 'dots' ? drawDots : drawHeat)();
                    buttons.forEach(function (b) { b.toggleAttribute('data-active', b.dataset.heatMode === mode); });
                }
                buttons.forEach(function (b) { b.addEventListener('click', function () { setMode(b.dataset.heatMode); }); });
                setMode(points.length < 200 ? 'dots' : 'heat');
            })();
        </script>
    @endif
</x-layouts.app>
