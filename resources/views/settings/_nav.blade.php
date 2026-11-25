<nav class="mb-6 flex gap-4 border-b border-zinc-800 text-sm">
    @foreach(['settings.index' => 'Account', 'settings.telegram' => 'Telegram', 'settings.two-factor' => 'Two-factor', 'settings.vault-key' => 'Vault key'] as $route => $label)
        <a href="{{ route($route) }}" class="-mb-px border-b-2 px-1 pb-2 {{ request()->routeIs($route) ? 'border-indigo-400 text-zinc-100' : 'border-transparent text-zinc-400 hover:text-zinc-100' }}">{{ $label }}</a>
    @endforeach
</nav>
