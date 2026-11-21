<x-layouts.app title="UFO Invaders">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">UFO Invaders</h1>
        <button type="button" data-game-fullscreen class="border border-zinc-600 bg-zinc-800 px-3 py-1.5 text-sm hover:bg-zinc-700">Fullscreen <span class="text-zinc-500 max-lg:hidden">(F)</span></button>
    </div>

    <div data-invaders class="mt-4 flex h-[calc(100dvh-10rem)] min-h-[420px] select-none flex-col gap-3 data-[fullscreen]:fixed data-[fullscreen]:inset-0 data-[fullscreen]:z-[100] data-[fullscreen]:mt-0 data-[fullscreen]:h-auto data-[fullscreen]:min-h-0 data-[fullscreen]:bg-black data-[fullscreen]:p-2">
        <div class="relative min-h-0 flex-1">
            <canvas class="size-full bg-black object-contain" style="image-rendering: pixelated" aria-label="UFO Invaders game" tabindex="0"></canvas>
            <button type="button" data-game-fullscreen aria-label="Exit fullscreen" class="absolute right-2 top-2 hidden border border-zinc-600 bg-zinc-900/80 px-3 py-1 text-sm in-data-[fullscreen]:block">&#10005;</button>
        </div>

        <div class="flex shrink-0 items-stretch justify-between gap-3 lg:hidden" style="touch-action: none">
            <div class="flex gap-2">
                <button type="button" data-game-key="left" class="h-16 w-20 border border-zinc-600 bg-zinc-800 text-xl active:bg-zinc-600" aria-label="Left">&larr;</button>
                <button type="button" data-game-key="right" class="h-16 w-20 border border-zinc-600 bg-zinc-800 text-xl active:bg-zinc-600" aria-label="Right">&rarr;</button>
            </div>
            <button type="button" data-game-key="fire" class="h-16 w-28 border border-indigo-500 bg-indigo-600 text-sm font-medium text-white active:bg-indigo-500">Fire</button>
        </div>

        <p class="shrink-0 text-xs text-zinc-500 max-lg:hidden">&larr; &rarr; / A D move &middot; Space / &uarr; / Z fire &middot; P pause &middot; M mute &middot; F fullscreen</p>
    </div>
</x-layouts.app>
