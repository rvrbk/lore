<x-layouts.app :title="$space->exists ? 'Edit space' : 'New space'">
    <h1 class="text-2xl font-semibold">{{ $space->exists ? 'Edit space' : 'New space' }}</h1>
    <form method="POST" action="{{ $space->exists ? route('spaces.update', $space) : route('spaces.store') }}" class="mt-6 space-y-4">
        @csrf
        @if($space->exists) @method('PUT') @endif
        <input name="name" value="{{ old('name', $space->name) }}" placeholder="Name" required class="w-full rounded-md border border-zinc-700 px-3 py-2">
        <textarea name="description" rows="3" placeholder="Description (optional)" class="w-full rounded-md border border-zinc-700 px-3 py-2">{{ old('description', $space->description) }}</textarea>
        @if($errors->any())<p class="text-sm text-red-400">{{ $errors->first() }}</p>@endif
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button>
    </form>
    @if($space->exists)
        <form method="POST" action="{{ route('spaces.destroy', $space) }}" onsubmit="return confirm('Delete this space and ALL its pages?')" class="mt-8">
            @csrf @method('DELETE')<button class="text-sm text-red-400 underline">Delete space</button>
        </form>
    @endif
</x-layouts.app>
