<x-layouts.app :title="$monitor->name">
    <a href="{{ route('monitors.index') }}" class="text-sm text-zinc-400 hover:underline">← Server health</a>
    <div class="mt-1 flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold">{{ $monitor->name }}</h1>
            <a href="{{ $monitor->url }}" target="_blank" rel="noopener noreferrer" class="block truncate text-sm text-zinc-400 hover:underline">{{ $monitor->url }}</a>
        </div>
        <form method="POST" action="{{ route('monitors.check-one', $monitor) }}">@csrf<button class="rounded border px-3 py-1.5 text-sm hover:bg-zinc-800">Check now</button></form>
    </div>

    <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-xs text-zinc-400">
        @foreach(['SSL certificate' => $monitor->ssl_expires_at, 'Domain' => $monitor->domain_expires_at] as $label => $expires)
            @php $days = $expires ? (int) floor(now()->diffInDays($expires, false)) : null; @endphp
            <div><dt class="inline">{{ $label }} expires:</dt> <dd class="inline {{ $days === null ? '' : ($days <= 7 ? 'text-red-400' : ($days <= 30 ? 'text-amber-300' : 'text-zinc-200')) }}">{{ $expires ? $expires->format('j M Y').' ('.($days < 0 ? 'expired' : $days.' days').')' : 'not checked yet' }}</dd></div>
        @endforeach
        @if($monitor->isPublic())<div><dt class="inline">Public status page:</dt> <dd class="inline"><a href="{{ route('status.show', $monitor->public_token) }}" target="_blank" rel="noopener" class="text-indigo-300 hover:underline">{{ route('status.show', $monitor->public_token) }}</a></dd></div>@endif
    </dl>

    <ul class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach($uptime as $label => $percent)
            <li class="border border-zinc-800 bg-zinc-900 p-3">
                <div class="text-xs uppercase text-zinc-400">Uptime · {{ $label }}</div>
                <div class="mt-1 text-xl {{ $percent === null ? 'text-zinc-500' : ($percent >= 99.5 ? 'text-emerald-400' : ($percent >= 95 ? 'text-amber-300' : 'text-red-400')) }}">{{ $percent === null ? '—' : $percent.'%' }}</div>
            </li>
        @endforeach
        <li class="border border-zinc-800 bg-zinc-900 p-3">
            <div class="text-xs uppercase text-zinc-400">Avg response · 24 h</div>
            <div class="mt-1 text-xl text-zinc-100">{{ $averageMs ? $averageMs.' ms' : '—' }}</div>
        </li>
    </ul>

    <section class="mt-8">
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-400">Last 24 hours</h2>
        @if($hours->isEmpty())
            <p class="text-sm text-zinc-400">No checks recorded yet. They appear once the scheduler has run.</p>
        @else
            <div class="flex h-8 items-stretch gap-0.5" role="img" aria-label="Uptime per hour over the last 24 hours">
                @foreach($hours as $hour)
                    <div class="flex-1 {{ $hour['up'] >= 100 ? 'bg-emerald-600' : ($hour['up'] >= 50 ? 'bg-amber-500' : 'bg-red-500') }}" title="{{ $hour['at']->format('D H:00') }}: {{ $hour['up'] }}% up"></div>
                @endforeach
            </div>
            <div class="mt-3 border border-zinc-800 bg-zinc-900 p-3">
                <div class="mb-1 text-xs text-zinc-400">Response time (ms) · {{ $checks->count() }} checks</div>
                @include('partials.sparkline', ['checks' => $checks, 'width' => 600, 'height' => 80, 'class' => 'h-20 w-full'])
            </div>
        @endif
    </section>

    <section class="mt-8">
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-400">Incidents · last 7 days</h2>
        <ul class="divide-y divide-zinc-800 border border-zinc-800 bg-zinc-900 text-sm">
            @forelse($incidents as $incident)
                <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                    <span class="text-red-400">Down since {{ $incident['from']->format('D j M, H:i') }}</span>
                    <span class="text-zinc-400">{{ $incident['to'] ? 'recovered '.$incident['to']->format('H:i').' · '.$incident['from']->diffForHumans($incident['to'], syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) : 'still down' }}</span>
                </li>
            @empty
                <li class="px-3 py-2 text-zinc-400">No downtime recorded. 🎉</li>
            @endforelse
        </ul>
    </section>
    @php $heading = 'mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-400'; @endphp

    <section class="mt-8">
        <h2 class="{{ $heading }}">Incidents</h2>
        <ul class="divide-y divide-zinc-800 border border-zinc-800 bg-zinc-900 text-sm">
            @forelse($siteIncidents as $incident)
                <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                    <a href="{{ route('incidents.index') }}" class="{{ $incident->isOpen() ? 'text-red-400' : 'text-zinc-200' }} hover:underline">{{ $incident->title }}</a>
                    <span class="text-xs text-zinc-400">{{ $incident->started_at->format('j M H:i') }} · {{ $incident->isOpen() ? 'open for '.$incident->duration() : 'lasted '.$incident->duration() }}</span>
                </li>
            @empty
                <li class="px-3 py-2 text-zinc-400">No incidents recorded.</li>
            @endforelse
        </ul>
    </section>

    @if($monitor->deploy_path)
        <section class="mt-8">
            <h2 class="{{ $heading }}">Deploys <a href="{{ route('deploy.index') }}" class="ml-2 normal-case hover:text-zinc-100">Open deploy page</a></h2>
            <ul class="divide-y divide-zinc-800 border border-zinc-800 bg-zinc-900 text-sm">
                @forelse($deployments as $deployment)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                        <span class="{{ $deployment->status === 'succeeded' ? 'text-emerald-400' : ($deployment->status === 'failed' ? 'text-red-400' : 'text-amber-300') }} uppercase">{{ $deployment->status }}
                            @if($deployment->smoke_status === 'failed')<span class="normal-case text-red-400">· site did not answer</span>@endif</span>
                        <span class="text-xs text-zinc-400">{{ $deployment->from_commit ? substr($deployment->from_commit, 0, 7) : '—' }} → {{ $deployment->to_commit ? substr($deployment->to_commit, 0, 7) : '—' }} · {{ $deployment->created_at->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="px-3 py-2 text-zinc-400">No deploys yet.</li>
                @endforelse
            </ul>
        </section>
    @endif

    @if($traffic && $traffic['users'] !== null)
        <section class="mt-8">
            <h2 class="{{ $heading }}">Traffic <span class="ml-2 normal-case">last 7 days</span></h2>
            <div class="border border-zinc-800 bg-zinc-900 p-3">@include('partials.analytics-tile', ['site' => $traffic])</div>
        </section>
    @endif

    <section class="mt-8">
        <h2 class="{{ $heading }}">Open tasks</h2>
        <ul class="divide-y divide-zinc-800 border border-zinc-800 bg-zinc-900 text-sm">
            @forelse($tasks as $task)
                <li class="flex items-center justify-between gap-2 px-3 py-2"><a href="{{ route('tasks.index', ['tag' => $tag]) }}" class="hover:underline">{{ $task->title }}</a><span class="text-xs {{ $task->isOverdue() ? 'text-red-400' : 'text-zinc-400' }}">{{ \App\Models\Task::STATUSES[$task->status] }}{{ $task->due_on ? ' · '.$task->due_on->format('j M') : '' }}</span></li>
            @empty
                <li class="px-3 py-2 text-zinc-400">None. Tag a task <a href="{{ route('tasks.index') }}" class="underline">#{{ $tag }}</a> to see it here.</li>
            @endforelse
        </ul>
    </section>

    <section class="mt-8">
        <h2 class="{{ $heading }}">Reminders</h2>
        <ul class="divide-y divide-zinc-800 border border-zinc-800 bg-zinc-900 text-sm">
            @forelse($reminders as $reminder)
                <li class="flex items-center justify-between gap-2 px-3 py-2"><a href="{{ route('reminders.index') }}" class="hover:underline">{{ $reminder->title }}</a><span class="text-xs {{ $reminder->isOverdue() ? 'text-red-400' : 'text-zinc-400' }}">{{ $reminder->due_at->format('j M Y H:i') }}</span></li>
            @empty
                <li class="px-3 py-2 text-zinc-400">No open reminders. Renewal countdowns appear here once the certificate and domain are looked up.</li>
            @endforelse
        </ul>
    </section>

    <section class="mt-8">
        <h2 class="{{ $heading }}">Wiki pages</h2>
        <ul class="divide-y divide-zinc-800 border border-zinc-800 bg-zinc-900 text-sm">
            @forelse($pages as $page)
                <li class="flex items-center justify-between gap-2 px-3 py-2"><a href="{{ route('pages.show', $page) }}" class="hover:underline">{{ $page->title }}</a><span class="text-xs text-zinc-400">{{ $page->space->name }}</span></li>
            @empty
                <li class="px-3 py-2 text-zinc-400">None. Tag a page <a href="{{ route('tags.index') }}" class="underline">#{{ $tag }}</a> to list it here.</li>
            @endforelse
        </ul>
    </section>
</x-layouts.app>
