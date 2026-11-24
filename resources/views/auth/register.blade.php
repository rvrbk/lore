<x-layouts.guest title="Create account">
    <h1 class="text-base">Create account</h1>
    <p class="mt-3 text-sm text-zinc-400">Choose a username and password. Your email is used to reset a forgotten password.</p>
    <form method="POST" action="{{ route('register') }}" class="mt-5 space-y-4">
        @csrf
        <label class="block text-sm">
            <span class="text-zinc-400">Username</span>
            <input name="username" value="{{ old('username') }}" required autofocus autocomplete="username" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
        </label>
        <label class="block text-sm">
            <span class="text-zinc-400">Email</span>
            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
        </label>
        <label class="block text-sm">
            <span class="text-zinc-400">Password (min. 8 characters)</span>
            <input type="password" name="password" required autocomplete="new-password" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
        </label>
        <label class="block text-sm">
            <span class="text-zinc-400">Confirm password</span>
            <input type="password" name="password_confirmation" required autocomplete="new-password" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
        </label>
        @if($errors->any())<p class="text-sm text-red-400">{{ $errors->first() }}</p>@endif
        <button class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Create account</button>
    </form>
    <p class="mt-4 text-sm text-zinc-400">Already have an account? <a href="{{ route('login') }}" class="underline">Log in</a>.</p>
</x-layouts.guest>
