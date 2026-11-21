<x-layouts.app title="UFO Invaders">
    <h1 class="text-2xl font-semibold">Arcade</h1>
    @include('partials.arcade-tabs')
    <p class="mt-3 text-sm text-zinc-400">UFO Invaders: the UFOs from the world map have come for the rest of Lore. Shoot them down, hide behind the bunkers and catch the unicorn for bonus points.</p>

    <div data-invaders class="mt-5 max-w-md select-none">
        <canvas class="w-full border border-zinc-700 bg-black" style="aspect-ratio: 240 / 280; image-rendering: pixelated" aria-label="UFO Invaders game" tabindex="0"></canvas>

        <div class="mt-3 flex items-stretch justify-between gap-3 lg:hidden" style="touch-action: none">
            <div class="flex gap-2">
                <button type="button" data-game-key="left" class="h-14 w-16 border border-zinc-600 bg-zinc-800 text-xl active:bg-zinc-600" aria-label="Left">&larr;</button>
                <button type="button" data-game-key="right" class="h-14 w-16 border border-zinc-600 bg-zinc-800 text-xl active:bg-zinc-600" aria-label="Right">&rarr;</button>
            </div>
            <button type="button" data-game-key="fire" class="h-14 w-24 border border-indigo-500 bg-indigo-600 text-xs font-medium text-white active:bg-indigo-500">Fire</button>
        </div>

        <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm text-zinc-400 max-lg:hidden">
            <dt class="text-zinc-200">&larr; &rarr; / A D</dt><dd>Move</dd>
            <dt class="text-zinc-200">Space / &uarr; / Z / W</dt><dd>Fire (hold to keep firing)</dd>
            <dt class="text-zinc-200">P / Esc &middot; M</dt><dd>Pause &middot; mute</dd>
        </dl>
    </div>
</x-layouts.app>
