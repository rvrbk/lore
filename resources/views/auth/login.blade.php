<x-layouts.guest title="Log in">
    <h1 class="text-base">Log in</h1>
    <form method="POST" action="{{ route('login') }}" class="mt-5 space-y-4">
        @csrf
        <label class="block text-sm">
            <span class="text-zinc-400">Username</span>
            <input name="username" value="{{ old('username') }}" required autofocus autocomplete="username" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
        </label>
        <label class="block text-sm">
            <span class="text-zinc-400">Password</span>
            <input type="password" name="password" required autocomplete="current-password" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
        </label>
        <label class="flex items-center gap-2 text-sm text-zinc-400">
            <input type="checkbox" name="remember" value="1" class="accent-indigo-500"> Keep me signed in
        </label>
        @if($errors->any())<p class="text-sm text-red-400">{{ $errors->first() }}</p>@endif
        <button class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Log in</button>
    </form>
</x-layouts.guest>
