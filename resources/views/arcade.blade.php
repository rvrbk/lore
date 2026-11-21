<x-layouts.app title="Arcade">
    <h1 class="text-2xl font-semibold">Arcade</h1>
    <p class="mt-2 text-sm text-zinc-400">Commander Lore: for when you're bored. Three worlds, one pogo stick, a raygun and a lot of candy.</p>

    <div data-keen class="mt-5 max-w-4xl select-none">
        <canvas class="w-full border border-zinc-700 bg-black" style="aspect-ratio: 320 / 192; image-rendering: pixelated" aria-label="Commander Lore game" tabindex="0"></canvas>

        <div class="mt-3 flex items-stretch justify-between gap-3 lg:hidden" style="touch-action: none">
            <div class="flex gap-2">
                <button type="button" data-keen-key="left" class="h-14 w-14 border border-zinc-600 bg-zinc-800 text-xl active:bg-zinc-600" aria-label="Left">&larr;</button>
                <button type="button" data-keen-key="right" class="h-14 w-14 border border-zinc-600 bg-zinc-800 text-xl active:bg-zinc-600" aria-label="Right">&rarr;</button>
            </div>
            <div class="flex gap-2">
                <button type="button" data-keen-key="pogo" class="h-14 w-14 border border-zinc-600 bg-zinc-800 text-xs active:bg-zinc-600">Pogo</button>
                <button type="button" data-keen-key="fire" class="h-14 w-14 border border-zinc-600 bg-zinc-800 text-xs active:bg-zinc-600">Fire</button>
                <button type="button" data-keen-key="jump" class="h-14 w-16 border border-indigo-500 bg-indigo-600 text-xs font-medium text-white active:bg-indigo-500">Jump</button>
            </div>
        </div>

        <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm text-zinc-400 max-lg:hidden">
            <dt class="text-zinc-200">&larr; &rarr; / A D</dt><dd>Run</dd>
            <dt class="text-zinc-200">Space / &uarr; / Z / W</dt><dd>Jump (hold for higher)</dd>
            <dt class="text-zinc-200">X / &darr; / S</dt><dd>Toggle pogo stick. Bounces on its own; hold jump for big bounces</dd>
            <dt class="text-zinc-200">C / Shift</dt><dd>Raygun (ammo is limited, grab the yellow batteries)</dd>
            <dt class="text-zinc-200">P / Esc &middot; M</dt><dd>Pause &middot; mute</dd>
        </dl>
    </div>
</x-layouts.app>
