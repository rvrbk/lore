<x-layouts.app title="Search">
    <h1 class="text-2xl font-semibold">Search{{ $q !== '' ? ': '.$q : '' }}</h1>
    @forelse($results as $type => $rows)
        <h2 class="mt-6 mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-400">{{ $type }}s <span class="ml-1 normal-case text-zinc-500">{{ $rows->count() }}</span></h2>
        <ul class="space-y-3">
            @foreach($rows as $row)
                <li class="rounded-lg border border-zinc-800 bg-zinc-900 p-3">
                    <a href="{{ $row['url'] }}" class="font-medium text-indigo-300 hover:underline">{{ $row['title'] }}</a>
                    @if($row['hint'] !== '')<p class="mt-1 text-sm text-zinc-400">{{ $row['hint'] }}</p>@endif
                </li>
            @endforeach
        </ul>
    @empty
        <p class="mt-6 text-sm text-zinc-400">{{ mb_strlen($q) < \App\Services\SiteSearch::MIN_LENGTH ? 'Type at least two letters to search.' : 'Nothing matches.' }}</p>
    @endforelse
</x-layouts.app>
