<x-layouts.guest title="Confirm your password">
    <h1 class="text-base">Confirm your password</h1>
    <p class="mt-2 text-sm text-zinc-400">This part of Lore asks for your password again before it opens.</p>
    <form method="POST" action="{{ route('password.confirm') }}" class="mt-5 space-y-4">
        @csrf
        <label class="block text-sm">
            <span class="text-zinc-400">Password</span>
            <input type="password" name="password" required autofocus autocomplete="current-password" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
        </label>
        @if($errors->any())<p class="text-sm text-red-400">{{ $errors->first() }}</p>@endif
        <button class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Confirm</button>
    </form>
</x-layouts.guest>
