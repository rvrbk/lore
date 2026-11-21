<div id="booking-request" class="scroll-mt-4">
@if(session('status'))<p class="mb-3 text-sm text-emerald-400">{{ session('status') }}</p>@endif
<form method="POST" action="{{ route('pointy.requests.send') }}" class="grid gap-2 sm:grid-cols-2">
    @csrf
    <select name="booking_type_id" required class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm">@foreach($bookingTypes as $bookingType)<option value="{{ $bookingType->id }}" @selected(old('booking_type_id') == $bookingType->id)>{{ $bookingType->name }}</option>@endforeach</select>
    <input type="email" name="guest_email" value="{{ old('guest_email') }}" placeholder="Their email" required class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
    <input name="guest_name" value="{{ old('guest_name') }}" placeholder="Their name (optional)" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
    <input name="note" value="{{ old('note') }}" placeholder="Message (optional)" class="rounded-md border border-zinc-700 px-3 py-1.5 text-sm">
    <div class="flex items-center gap-3 sm:col-span-2">
        <button class="rounded border px-3 py-1.5 text-sm hover:bg-zinc-800">Send request</button>
        @foreach(['booking_type_id', 'guest_email', 'guest_name', 'note'] as $field)@error($field)<span class="text-sm text-red-400">{{ $errors->first($field) }}</span>@enderror @endforeach
    </div>
</form>
</div>
@if(session('status') || $errors->hasAny(['booking_type_id', 'guest_email', 'guest_name', 'note']))
    <script>document.getElementById('booking-request').scrollIntoView({ block: 'center' });</script>
@endif
