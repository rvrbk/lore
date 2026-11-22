@php($nodes = $byParent->get($parentId ?? '', collect()))
@if($nodes->isNotEmpty())
<ul class="{{ ($parentId ?? '') === '' ? '' : 'ml-4 border-l border-zinc-800 pl-2' }} space-y-0.5 text-sm">
    @foreach($nodes as $node)
        <li>
            <a href="{{ route('pages.show', $node) }}" class="block rounded px-2 py-1 hover:bg-zinc-800 {{ isset($current) && $current?->id === $node->id ? 'bg-indigo-500/15 font-medium text-indigo-300' : '' }}"><span title="{{ $node->weather()['label'] }}" class="mr-1 text-xs">{{ $node->weather()['icon'] }}</span>{{ $node->title }}</a>
            @include('partials.tree', ['byParent' => $byParent, 'parentId' => $node->id, 'current' => $current ?? null])
        </li>
    @endforeach
</ul>
@endif
