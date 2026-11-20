<x-layouts.app title="System settings">
    <h1 class="text-2xl font-semibold">System settings</h1>
    @if(session('status'))<p class="mt-3 text-sm text-emerald-400">{{ session('status') }}</p>@endif

    <form method="POST" action="{{ route('settings.update') }}" class="mt-6 max-w-md border border-zinc-800 bg-zinc-900 p-4">
        @csrf @method('PUT')
        <fieldset>
            <legend class="text-sm font-semibold text-zinc-200">Time notation</legend>
            <p class="mt-1 text-xs text-zinc-400">Used by the world clocks on Home and by Pointy (booking pages, emails and bookings).</p>
            <label class="mt-3 flex items-center gap-2 text-sm"><input type="radio" name="time_format" value="24" @checked($timeFormat === '24')>24-hour <span class="text-zinc-500">(15:30)</span></label>
            <label class="mt-2 flex items-center gap-2 text-sm"><input type="radio" name="time_format" value="12" @checked($timeFormat === '12')>12-hour <span class="text-zinc-500">(3:30 PM)</span></label>
            @error('time_format')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
        </fieldset>
        <button class="mt-4 rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Save</button>
    </form>
</x-layouts.app>
