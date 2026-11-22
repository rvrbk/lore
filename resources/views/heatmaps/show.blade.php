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

    @if($path === '')
        <p class="mt-6 text-sm text-zinc-400">No {{ $device }} clicks in the last {{ $days }} days. Once the tracker is live on {{ $monitor->host() }}, clicks appear here.</p>
    @else
        <p class="mt-2 text-xs text-zinc-500">The live page is shown behind the clicks; a site that forbids framing shows blank, the clicks still line up by position.</p>
        <div class="mt-3 overflow-x-auto border border-zinc-800">
            <div id="heat-stage" class="relative bg-white" style="width: {{ $width }}px">
                <iframe src="{{ $pageUrl }}" title="{{ $monitor->name }} {{ $path }}" tabindex="-1" class="block border-0" style="width: {{ $width }}px; height: 1200px"></iframe>
                <canvas id="heat-canvas" class="pointer-events-none absolute left-0 top-0"></canvas>
            </div>
        </div>
        <script>
            (function () {
                var points = @json($points);
                var width = {{ $width }};
                var stage = document.getElementById('heat-stage');
                var canvas = document.getElementById('heat-canvas');
                var maxY = points.reduce(function (max, p) { return Math.max(max, p[1]); }, 0);
                var height = Math.min(15000, Math.max(1200, maxY + 900));
                canvas.width = width;
                canvas.height = height;
                stage.querySelector('iframe').style.height = height + 'px';

                var radius = width > 600 ? 28 : 18;
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

                var ramp = document.createElement('canvas');
                ramp.width = 1;
                ramp.height = 256;
                var rctx = ramp.getContext('2d');
                var colors = rctx.createLinearGradient(0, 0, 0, 256);
                [[0.0, 'rgba(0,0,255,0)'], [0.25, 'rgb(0,0,255)'], [0.5, 'rgb(0,255,0)'], [0.75, 'rgb(255,255,0)'], [1.0, 'rgb(255,0,0)']].forEach(function (s) { colors.addColorStop(s[0], s[1]); });
                rctx.fillStyle = colors;
                rctx.fillRect(0, 0, 1, 256);
                var palette = rctx.getImageData(0, 0, 1, 256).data;

                var ctx = canvas.getContext('2d');
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
            })();
        </script>
    @endif
</x-layouts.app>
