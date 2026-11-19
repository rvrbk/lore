<x-layouts.app title="Incidents">
    <h1 class="text-2xl font-semibold">Incidents</h1>
    <p class="mt-2 text-sm text-zinc-400">Every outage, failed deploy check, expiring certificate or domain, and full disk lands here. Each one gets a card on the <a href="{{ route('tasks.index') }}" class="underline">board</a> and a Telegram message, and resolves itself when the problem goes away.</p>

    <section class="mt-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-400">Open</h2>
        @forelse($open as $incident)
            @include('incidents.row', ['incident' => $incident])
        @empty
            <p class="text-sm text-zinc-400">Nothing is broken. 🎉</p>
        @endforelse
    </section>

    @if($resolved->isNotEmpty())
        <section class="mt-10">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-400">Resolved</h2>
            @foreach($resolved as $incident)
                @include('incidents.row', ['incident' => $incident])
            @endforeach
        </section>
    @endif
</x-layouts.app>
