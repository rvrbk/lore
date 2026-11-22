<x-layouts.app title="Arcade corner">
    <h1 class="text-2xl font-semibold">Arcade corner 🛸</h1>
    <p class="mt-2 text-sm text-zinc-400">Shoot UFOs: click or tap them, or aim with the mouse and press space. You have 30 seconds. Beat <span class="text-indigo-400">{{ number_format($target) }}</span>, the number of visitors your sites had this week.</p>
    <div class="mt-4 max-w-3xl border border-zinc-800 bg-zinc-900 p-2" data-arcade data-target="{{ $target }}">
        <div class="mb-2 flex items-center justify-between text-xs text-zinc-400">
            <span>Score <span class="text-lg font-semibold text-indigo-400" data-arcade-score>0</span></span>
            <span>Time <span data-arcade-time>30</span>s</span>
            <span>Best <span data-arcade-best>0</span></span>
            <button type="button" data-arcade-start class="rounded border px-3 py-1 text-sm text-zinc-100 hover:bg-zinc-800">Start</button>
        </div>
        <canvas data-arcade-canvas width="720" height="420" class="block h-auto w-full touch-none bg-zinc-950" aria-label="UFO shooting game"></canvas>
        <p class="mt-2 text-xs text-zinc-500" data-arcade-message>Press Start.</p>
    </div>
</x-layouts.app>
