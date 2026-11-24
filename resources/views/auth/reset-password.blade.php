<x-layouts.guest title="Choose a new password">
    <h1 class="text-base">Choose a new password</h1>
    <form method="POST" action="{{ route('password.update') }}" class="mt-5 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label class="block text-sm">
            <span class="text-zinc-400">Email</span>
            <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
        </label>
        <label class="block text-sm">
            <span class="text-zinc-400">New password (min. 8 characters)</span>
            <input type="password" name="password" required autofocus autocomplete="new-password" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
        </label>
        <label class="block text-sm">
            <span class="text-zinc-400">Confirm password</span>
            <input type="password" name="password_confirmation" required autocomplete="new-password" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
        </label>
        @if($errors->any())<p class="text-sm text-red-400">{{ $errors->first() }}</p>@endif
        <button class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Change password</button>
    </form>
</x-layouts.guest>
