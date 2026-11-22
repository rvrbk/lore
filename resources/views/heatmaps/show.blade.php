<x-layouts.app :title="'Heatmap · '.$monitor->name">
    <a href="{{ route('heatmaps.index') }}" class="text-sm text-zinc-400 hover:underline">← Heatmaps</a>
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

    @if($path === '')
        <p class="mt-6 text-sm text-zinc-400">No {{ $device }} clicks in the last {{ $days }} days. Once the tracker is live on {{ $monitor->host() }}, clicks appear here.</p>
    @else
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm" role="group" aria-label="View">
            <button type="button" data-heat-mode="heat" class="rounded-md border border-zinc-700 px-3 py-1 data-[active]:bg-indigo-600 data-[active]:text-white">Heat</button>
            <button type="button" data-heat-mode="dots" class="rounded-md border border-zinc-700 px-3 py-1 data-[active]:bg-indigo-600 data-[active]:text-white">Dots</button>
            <label class="ml-2 flex items-center gap-1.5 text-zinc-400"><input type="checkbox" id="heat-hide-page"> Hide page</label>
            @if($canCapture)
                <form method="POST" action="{{ route('heatmaps.refresh', $monitor) }}" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Capturing…'">@csrf
                    <input type="hidden" name="path" value="{{ $path }}"><input type="hidden" name="device" value="{{ $device }}"><input type="hidden" name="days" value="{{ $days }}">
                    <button class="rounded-md border border-zinc-700 px-3 py-1 hover:bg-zinc-800">{{ $screenshotHeight ? 'Retake screenshot' : 'Take screenshot' }}</button>
                </form>
            @endif
            <span class="text-xs text-zinc-500">Heat blurs busy areas red; Dots marks every single click.{{ $screenshotHeight ? '' : ' No screenshot yet, so the live page is shown (a site that forbids framing shows blank).' }}</span>
        </div>
        <div class="mt-3 overflow-x-auto border border-zinc-800">
            <div id="heat-stage" class="relative bg-white" style="width: {{ $width }}px">
                @if($screenshotHeight)
                    <img id="heat-frame" src="{{ route('heatmaps.screenshot', ['monitor' => $monitor, 'path' => $path, 'device' => $device]) }}" alt="Screenshot of {{ $monitor->name }} {{ $path }}" class="block max-w-none" style="width: {{ $width }}px; height: {{ $screenshotHeight }}px">
                @else
                    <iframe id="heat-frame" src="{{ $pageUrl }}" title="{{ $monitor->name }} {{ $path }}" tabindex="-1" class="block border-0" style="width: {{ $width }}px; height: 1200px"></iframe>
                @endif
                <canvas id="heat-canvas" class="pointer-events-none absolute left-0 top-0"></canvas>
            </div>
        </div>
        <script>
            (function () {
                var points = @json($points);
                var width = {{ $width }};
                var stage = document.getElementById('heat-stage');
                var frame = document.getElementById('heat-frame');
                var canvas = document.getElementById('heat-canvas');
                var ctx = canvas.getContext('2d');
                var maxY = points.reduce(function (max, p) { return Math.max(max, p[1]); }, 0);
                var height = {{ $screenshotHeight ?? 'null' }} || Math.min(15000, Math.max(1200, maxY + 900));
                canvas.width = width;
                canvas.height = height;
                frame.style.height = height + 'px';

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
                    ctx.putImageData(image, 0, 0);
                }

                function drawDots() {
                    points.forEach(function (p) {
                        var x = p[0] / 1000 * width, y = p[1];
                        ctx.beginPath();
                        ctx.arc(x, y, 6, 0, Math.PI * 2);
                        ctx.fillStyle = 'rgba(239,68,68,0.55)';
                        ctx.fill();
                        ctx.lineWidth = 1.5;
                        ctx.strokeStyle = 'rgba(255,255,255,0.95)';
                        ctx.stroke();
                    });
                }

                var buttons = document.querySelectorAll('[data-heat-mode]');
                function setMode(mode) {
                    ctx.clearRect(0, 0, width, height);
                    (mode === 'dots' ? drawDots : drawHeat)();
                    buttons.forEach(function (b) { b.toggleAttribute('data-active', b.dataset.heatMode === mode); });
                }
                buttons.forEach(function (b) { b.addEventListener('click', function () { setMode(b.dataset.heatMode); }); });
                document.getElementById('heat-hide-page').addEventListener('change', function (e) {
                    frame.style.visibility = e.target.checked ? 'hidden' : 'visible';
                    stage.style.background = e.target.checked ? '#18181b' : '#fff';
                });
                setMode('heat');
            })();
        </script>
    @endif
</x-layouts.app>
