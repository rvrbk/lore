@php($label = $activeDeployments->isEmpty() ? '' : ($activeDeployments->contains('status', 'running') ? 'Deploying' : 'Deploy queued').': '.($activeDeployments->count() > 2 ? $activeDeployments->count().' sites' : $activeDeployments->pluck('site')->join(', ')))
<a href="{{ route('deploy.index') }}" data-deploy-indicator data-url="{{ route('deploy.active') }}" @if($activeDeployments->isEmpty()) hidden @endif title="Open Deploy" class="flex items-center gap-2 text-xs text-amber-300 lg:border lg:border-amber-500/60 lg:bg-amber-500/10 lg:px-2 lg:py-1 lg:hover:bg-amber-500/20">
    <span class="size-3 animate-pulse rounded-full bg-amber-400 lg:size-2 lg:rounded-none"></span><span data-deploy-text class="max-lg:sr-only">{{ $label }}</span>
</a>
