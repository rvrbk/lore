<x-layouts.app title="Approvals">
    <h1 class="text-2xl font-semibold">Settings</h1>
    <div class="mt-4">@include('settings._nav')</div>
    <p class="mt-2 max-w-2xl text-sm text-zinc-400">New accounts cannot log in until you approve them. You get a Telegram message when someone registers.</p>

    @if(session('status'))<p class="mt-4 text-sm text-emerald-400">{{ session('status') }}</p>@endif

    @forelse($pendingUsers as $pending)
        <section class="mt-4 flex items-center justify-between gap-4 border border-zinc-800 bg-zinc-900 p-4 text-sm">
            <div>
                <div class="font-medium">{{ $pending->username }}</div>
                <div class="text-zinc-400">{{ $pending->email }} · registered {{ $pending->created_at->diffForHumans() }}</div>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('admin.users.approve', $pending) }}">@csrf<button class="rounded bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">Approve</button></form>
                <form method="POST" action="{{ route('admin.users.reject', $pending) }}" onsubmit="return confirm('Reject and remove {{ $pending->username }}?')">@csrf @method('DELETE')<button class="rounded border px-3 py-1.5 text-red-400 hover:bg-red-500/10">Reject</button></form>
            </div>
        </section>
    @empty
        <p class="mt-6 text-sm text-zinc-400">Nobody is waiting for approval.</p>
    @endforelse
</x-layouts.app>
