<x-layouts.app title="Deploy">
    <h1 class="text-2xl font-semibold">Deploy</h1>
    <p class="mt-2 text-sm text-zinc-400">Pulls the latest commits of whichever branch each site's checkout is on, then installs packages, builds assets, migrates and recaches, depending on the kind of site. The scheduler runs it within a minute of pressing a button.</p>

    @unless($enabled)
        <p class="mt-4 border border-zinc-700 bg-zinc-900 p-3 text-sm text-zinc-400">Deploying is switched off here. Set <code>DEPLOY_ENABLED=true</code> in the server's .env to use it.</p>
    @endunless

    <div class="mt-5 flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('deploy.store') }}">
            @csrf
            <button @disabled(! $enabled || $sites->every(fn (array $site) => $site['active'])) class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">Deploy all sites</button>
        </form>
        <span class="text-xs text-zinc-500">Sites need a server folder, set in "Add a site" or under Edit apps on Server health.</span>
    </div>

    <ul class="mt-4 grid gap-3 lg:grid-cols-2">
        @foreach($sites as $site)
            @php $status = $site['status']; @endphp
            <li class="border border-zinc-800 bg-zinc-900 p-4 text-sm">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0"><div class="truncate font-medium text-zinc-100">{{ $site['name'] }}</div><div class="truncate text-xs text-zinc-500">{{ $site['path'] }}</div></div>
                    <form method="POST" action="{{ route('deploy.store') }}">
                        @csrf<input type="hidden" name="path" value="{{ $site['path'] }}">
                        <button @disabled(! $enabled || $site['active']) class="shrink-0 rounded border px-3 py-1 text-xs hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-50">{{ $site['active'] ? 'Deploying…' : 'Deploy' }}</button>
                    </form>
                </div>
                @if($status)
                    <p class="mt-2 break-words text-xs text-zinc-400"><span class="text-zinc-100">{{ $status['branch'] ?? 'no branch' }}</span> · <span class="text-indigo-300">{{ $status['commit'] }}</span> {{ $status['subject'] }}</p>
                    @if($status['error'])
                        <p class="mt-2 text-xs text-red-400">{{ $status['error'] }}</p>
                    @elseif($status['behind'] > 0)
                        <p class="mt-2 text-xs text-amber-300">{{ $status['behind'] }} new {{ Str::plural('commit', $status['behind']) }} on origin</p>
                        <ul class="mt-1 space-y-0.5 text-xs text-zinc-400">@foreach($status['incoming'] as $line)<li class="break-words">{{ $line }}</li>@endforeach</ul>
                    @else
                        <p class="mt-2 text-xs text-emerald-400">Up to date</p>
                    @endif
                    <p class="mt-1 text-xs text-zinc-500">Checked {{ \Illuminate\Support\Carbon::parse($status['checked_at'])->diffForHumans() }}</p>
                @else
                    <p class="mt-2 text-xs text-zinc-400">Not checked yet.</p>
                @endif
            </li>
        @endforeach
    </ul>

    <h2 class="mt-8 mb-2">History</h2>
    <ul class="space-y-2">
        @forelse($deployments as $deployment)
            @php
                $style = match ($deployment->status) {
                    'succeeded' => 'text-emerald-400',
                    'failed' => 'text-red-400',
                    default => 'text-amber-300',
                };
            @endphp
            <li class="border border-zinc-800 bg-zinc-900 p-3 text-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium {{ $style }}"><span class="uppercase">{{ $deployment->status }}</span> <span class="text-zinc-100">{{ $deployment->site ?? 'lore' }}</span></span>
                    <span class="text-xs text-zinc-400">{{ $deployment->branch }} · {{ $deployment->from_commit ? substr($deployment->from_commit, 0, 7) : '—' }} → {{ $deployment->to_commit ? substr($deployment->to_commit, 0, 7) : '—' }} · {{ $deployment->created_at->diffForHumans() }}</span>
                </div>
                @if($deployment->output)
                    <details class="mt-2"><summary class="cursor-pointer text-xs text-zinc-400">Output</summary><pre class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap break-words bg-zinc-950 p-3 text-xs text-zinc-300">{{ $deployment->output }}</pre></details>
                @elseif($deployment->isActive())
                    <p class="mt-1 text-xs text-zinc-400">{{ $deployment->status === 'requested' ? 'Waiting for the scheduler (up to a minute)…' : 'Running…' }}</p>
                @endif
            </li>
        @empty
            <li class="text-sm text-zinc-400">No deployments yet.</li>
        @endforelse
    </ul>

    @if($sites->contains('active', true))<script>setTimeout(() => location.reload(), 5000)</script>@endif
</x-layouts.app>
