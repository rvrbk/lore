<x-layouts.app title="Root Cause">
    <h1 class="text-2xl font-semibold">Root Cause</h1>
    <p class="mt-1 text-sm text-zinc-400">Everything is on fire, but only one service is really broken. Arrows point at what a service depends on, and anything that depends on a broken service fails too. Find the culprit using as few probes as you can.</p>

    <div data-root-cause class="mt-4">
        <div data-rc-tabs class="flex flex-wrap gap-2"></div>
        <div data-rc-stage class="mt-4"></div>
    </div>
</x-layouts.app>
