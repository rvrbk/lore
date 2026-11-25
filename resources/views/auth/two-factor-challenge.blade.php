<x-layouts.guest title="Two-factor authentication">
    <h1 class="text-base">Two-factor authentication</h1>
    <p class="mt-2 text-sm text-zinc-400">Enter the six digit code from your authenticator app, or one of your recovery codes.</p>
    <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-5 space-y-4">
        @csrf
        <label class="block text-sm">
            <span class="text-zinc-400">Code</span>
            <input name="code" required autofocus autocomplete="one-time-code" inputmode="text" class="mt-1 w-full rounded-md border border-zinc-700 px-3 py-2">
        </label>
        @if($errors->any())<p class="text-sm text-red-400">{{ $errors->first() }}</p>@endif
        <button class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Continue</button>
    </form>
</x-layouts.guest>
