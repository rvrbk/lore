<x-layouts.app title="Settings">
    <h1 class="text-2xl font-semibold">Settings</h1>
    <div class="mt-4">@include('settings._nav')</div>

    @if(session('status'))<p class="mb-4 text-sm text-emerald-400">{{ session('status') }}</p>@endif

    <form method="POST" action="{{ route('settings.update') }}" class="max-w-xl space-y-5">
        @csrf @method('PUT')
        <label class="block text-sm">
            <span class="text-zinc-400">Email address</span>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" autocomplete="email" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
            <span class="mt-1 block text-xs text-zinc-500">Only used to send you a link when you forget your password. Without one, a forgotten password cannot be reset.</span>
            @error('email')<span class="mt-1 block text-red-400">{{ $message }}</span>@enderror
        </label>
        <label class="block text-sm">
            <span class="text-zinc-400">Job Scout email</span>
            <input type="email" name="job_scout_email" value="{{ old('job_scout_email', $user->job_scout_email) }}" maxlength="255" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
            <span class="mt-1 block text-xs text-zinc-500">The email of your Job Scout account. It shows your new vacancy matches on the dashboard. Leave it empty if you have no Job Scout account.</span>
            @error('job_scout_email')<span class="mt-1 block text-red-400">{{ $message }}</span>@enderror
        </label>
        <button class="rounded border px-3 py-1.5 text-sm hover:bg-zinc-800">Save</button>
    </form>
</x-layouts.app>
