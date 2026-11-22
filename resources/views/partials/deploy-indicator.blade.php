@php($label = $activeDeployments->isEmpty() ? '' : ($activeDeployments->contains('status', 'running') ? 'Deploying' : 'Deploy queued').': '.($activeDeployments->count() > 2 ? $activeDeployments->count().' sites' : $activeDeployments->pluck('site')->join(', ')))
<a href="{{ route('deploy.index') }}" data-deploy-indicator data-url="{{ route('deploy.active') }}" @if($activeDeployments->isEmpty()) hidden @endif title="Open Deploy" class="flex items-center gap-2 border border-amber-500/60 bg-amber-500/10 px-2 py-1 text-xs text-amber-300 hover:bg-amber-500/20">
    <span class="size-2 animate-pulse bg-amber-400"></span><span data-deploy-text>{{ $label }}</span>
</a>
