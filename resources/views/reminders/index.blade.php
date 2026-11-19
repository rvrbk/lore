<x-layouts.app title="Reminders">
    <h1 class="text-2xl font-semibold">Reminders</h1>
    <details class="mt-4 rounded-lg border border-zinc-800 bg-zinc-900 p-3" @if($errors->any()) open @endif>
        <summary class="cursor-pointer text-sm font-medium">New reminder</summary>
        <div class="mt-3">@include('partials.reminder-form', ['pages' => $pages, 'tasks' => $tasks])</div>
    </details>
    <ul class="mt-6 space-y-2">
        @forelse($open as $reminder) @include('partials.reminder-row', ['reminder' => $reminder]) @empty <li class="text-sm text-zinc-400">No open reminders.</li> @endforelse
    </ul>
    @if($done->isNotEmpty())
        <h2 class="mt-8 mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-400">Recently completed</h2>
        <ul class="space-y-2">@foreach($done as $reminder) @include('partials.reminder-row', ['reminder' => $reminder]) @endforeach</ul>
    @endif
</x-layouts.app>
