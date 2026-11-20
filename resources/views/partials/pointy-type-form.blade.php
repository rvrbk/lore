@php
    $type = $type ?? null;
    $selectedDays = old('days', $type?->days ?? [1, 2, 3, 4, 5]);
    $weekdays = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    $field = 'rounded-md border border-zinc-700 px-3 py-1.5 text-sm';
@endphp
<form method="POST" action="{{ $type ? route('pointy.types.update', $type) : route('pointy.types.store') }}" class="grid gap-3 sm:grid-cols-2">
    @csrf
    @if($type) @method('PUT') @endif
    <input name="name" value="{{ old('name', $type?->name) }}" placeholder="Name, e.g. 30 min intro call" required maxlength="100" aria-label="Name" class="{{ $field }}">
    <input name="slug" value="{{ old('slug', $type?->slug) }}" placeholder="Link name (optional), e.g. intro" aria-label="Link name" class="{{ $field }}">
    <input name="description" value="{{ old('description', $type?->description) }}" placeholder="Short description (optional)" aria-label="Description" class="{{ $field }} sm:col-span-2">
    <label class="text-xs text-zinc-400">Length (minutes)<input type="number" name="duration_minutes" value="{{ old('duration_minutes', $type?->duration_minutes ?? 30) }}" min="5" max="480" required class="{{ $field }} mt-1 w-full"></label>
    <label class="text-xs text-zinc-400">Buffer between meetings (minutes)<input type="number" name="buffer_minutes" value="{{ old('buffer_minutes', $type?->buffer_minutes ?? 0) }}" min="0" max="240" required class="{{ $field }} mt-1 w-full"></label>
    <label class="text-xs text-zinc-400 sm:col-span-2">Your time zone (the hours below are in this zone)
        <select name="timezone" class="{{ $field }} mt-1 w-full">@foreach($timezones as $zone)<option value="{{ $zone }}" @selected(old('timezone', $type?->timezone ?? config('app.timezone')) === $zone)>{{ str_replace('_', ' ', $zone) }}</option>@endforeach</select>
    </label>
    <div class="flex flex-wrap gap-3 text-sm sm:col-span-2">
        @foreach($weekdays as $number => $label)
            <label class="flex items-center gap-1"><input type="checkbox" name="days[]" value="{{ $number }}" @checked(in_array($number, $selectedDays))>{{ $label }}</label>
        @endforeach
    </div>
    <label class="text-xs text-zinc-400">From<input type="time" name="start_time" value="{{ old('start_time', $type?->start_time ?? '09:00') }}" required class="{{ $field }} mt-1 w-full"></label>
    <label class="text-xs text-zinc-400">Until<input type="time" name="end_time" value="{{ old('end_time', $type?->end_time ?? '17:00') }}" required class="{{ $field }} mt-1 w-full"></label>
    <label class="text-xs text-zinc-400">Minimum notice (hours)<input type="number" name="min_notice_hours" value="{{ old('min_notice_hours', $type?->min_notice_hours ?? 4) }}" min="0" max="720" required class="{{ $field }} mt-1 w-full"></label>
    <label class="text-xs text-zinc-400">Bookable this many days ahead<input type="number" name="max_days_ahead" value="{{ old('max_days_ahead', $type?->max_days_ahead ?? 30) }}" min="1" max="365" required class="{{ $field }} mt-1 w-full"></label>
    <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $type?->is_active ?? true))>Accepting bookings</label>
    @if($errors->any())<p class="text-sm text-red-400 sm:col-span-2">{{ $errors->first() }}</p>@endif
    <div class="sm:col-span-2"><button class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">{{ $type ? 'Save' : 'Add meeting type' }}</button></div>
</form>
