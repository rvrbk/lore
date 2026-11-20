<x-layouts.app title="Home">
    <div class="flex items-start justify-between gap-4">
        <h1 class="text-2xl font-semibold">Welcome back</h1>
        <a href="{{ route('export.all') }}" class="rounded border px-3 py-1.5 text-sm hover:bg-zinc-800" title="Download every page as Markdown files in a .zip">Export everything</a>
    </div>

    @if($jobScout)
        <a href="{{ $jobScout['url'] }}" target="_blank" rel="noopener" class="mt-6 flex items-center justify-between border border-zinc-800 bg-zinc-900 p-3 hover:border-zinc-500">
            <span class="text-sm text-zinc-400">New vacancies in Job Scout</span>
            <span class="text-2xl font-semibold {{ $jobScout['new'] ? 'text-indigo-400' : 'text-zinc-500' }}">{{ $jobScout['new'] }}</span>
        </a>
    @endif

    <section class="mt-12">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Server health
                @if($monitors->isNotEmpty())
                    <span class="ml-2 normal-case {{ $monitors->contains('is_up', false) ? 'text-red-400' : 'text-emerald-400' }}">{{ $monitors->where('is_up', true)->count() }}/{{ $monitors->count() }} up</span>
                @endif
            </h2>
            <div class="flex items-center gap-3 text-sm">
                @if($monitors->isNotEmpty())
                    <form method="POST" action="{{ route('monitors.check') }}">@csrf<button class="text-zinc-400 hover:text-zinc-100">Check now</button></form>
                @endif
                <a href="{{ route('monitors.index') }}" class="text-zinc-400 hover:text-zinc-100">Manage</a>
            </div>
        </div>
        @if($monitors->isEmpty())
            <p class="text-sm text-zinc-400">No apps monitored yet. <a href="{{ route('monitors.index') }}" class="underline">Add the first one</a>.</p>
        @else
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">@foreach($monitors as $monitor) @include('partials.monitor-tile', ['monitor' => $monitor, 'sparks' => $sparks]) @endforeach</ul>
        @endif
    </section>

    @if($analytics)
        <section class="mt-12">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Analytics <span class="ml-2 normal-case">last 7 days · daily users</span></h2>
                <div class="flex items-center gap-3 text-sm">
                    <a href="{{ route('analytics.index') }}" class="text-zinc-400 hover:text-zinc-100">Manage</a>
                </div>
            </div>
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($analytics as $site)
                    <li class="border border-zinc-800 bg-zinc-900 p-3">@include('partials.analytics-tile', ['site' => $site])</li>
                @endforeach
            </ul>
            <div class="mt-4">@include('partials.world-map')</div>
        </section>
    @endif

    @if($siteUsers)
        <section class="mt-12">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-400">Users <span class="ml-2 normal-case">rows in each site's users table</span></h2>
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($siteUsers as $site)
                    <li class="flex items-center justify-between border border-zinc-800 bg-zinc-900 p-3">
                        <span class="truncate text-sm font-medium text-zinc-100">{{ $site['name'] }}</span>
                        @if($site['users'] === null)
                            <span class="text-sm text-zinc-500" title="Not a Laravel app, or its users table could not be read">n/a</span>
                        @else
                            <span class="text-2xl font-semibold text-indigo-400">{{ number_format($site['users']) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="mt-12">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-400">Due now</h2>
        @if($due->isEmpty())
            <p class="text-sm text-zinc-400">Nothing due. 🎉</p>
        @else
            <ul class="space-y-2">@foreach($due as $reminder) @include('partials.reminder-row', ['reminder' => $reminder]) @endforeach</ul>
        @endif
    </section>

    <section class="mt-12">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-400">Next 14 days</h2>
        @if($upcoming->isEmpty())
            <p class="text-sm text-zinc-400">No upcoming reminders. <a href="{{ route('reminders.index') }}" class="underline">Add one</a>.</p>
        @else
            <ul class="space-y-2">@foreach($upcoming as $reminder) @include('partials.reminder-row', ['reminder' => $reminder]) @endforeach</ul>
        @endif
    </section>

    <section class="mt-12">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-400">Recently updated pages</h2>
        <ul class="divide-y divide-zinc-800 rounded-lg border border-zinc-800 bg-zinc-900 text-sm">
            @forelse($recent as $p)
                <li class="flex justify-between px-3 py-2"><a href="{{ route('pages.show', $p) }}" class="font-medium hover:underline">{{ $p->title }}</a><span class="text-zinc-400">{{ $p->space->name }} · {{ $p->updated_at->diffForHumans() }}</span></li>
            @empty
                <li class="px-3 py-2 text-zinc-400">No pages yet. Create a space to get started.</li>
            @endforelse
        </ul>
    </section>
</x-layouts.app>
