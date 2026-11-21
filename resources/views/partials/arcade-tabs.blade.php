<nav class="mt-3 flex gap-2 text-sm">
    <a href="{{ route('arcade') }}" class="border px-3 py-1 {{ request()->routeIs('arcade') ? 'border-indigo-500 bg-indigo-600 text-white' : 'border-zinc-700 text-zinc-300 hover:bg-zinc-800' }}">Commander Lore</a>
    <a href="{{ route('arcade.invaders') }}" class="border px-3 py-1 {{ request()->routeIs('arcade.invaders') ? 'border-indigo-500 bg-indigo-600 text-white' : 'border-zinc-700 text-zinc-300 hover:bg-zinc-800' }}">UFO Invaders</a>
</nav>
