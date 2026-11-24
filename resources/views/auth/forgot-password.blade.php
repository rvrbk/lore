<x-layouts.guest title="Forgot password">
    <h1 class="text-base">Forgot your password?</h1>
    <p class="mt-3 text-sm text-zinc-400">Enter the email address on your account and we will send you a link to choose a new password. Accounts without an email address cannot be reset this way.</p>
    <form method="POST" action="{{ route('password.email') }}" class="mt-5 space-y-4">
        @csrf
        <label class="block text-sm">
            <span class="text-zinc-400">Email</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
        </label>
        @if(session('status'))<p class="text-sm text-emerald-400">{{ session('status') }}</p>@endif
        @if($errors->any())<p class="text-sm text-red-400">{{ $errors->first() }}</p>@endif
        <button class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Send reset link</button>
    </form>
    <p class="mt-4 text-sm text-zinc-400"><a href="{{ route('login') }}" class="underline">Back to log in</a></p>
</x-layouts.guest>
