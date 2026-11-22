<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => $type->name.' · Pointy'])
    <meta name="robots" content="noindex">
    @if($css = $type->publicCss())<style>{!! $css !!}</style>@endif
</head>
<body class="pointy min-h-screen bg-zinc-950 text-zinc-200 antialiased">
@php($field = 'pointy-input w-full rounded-md border border-zinc-700 px-3 py-1.5 text-sm')
<main class="pointy-main mx-auto max-w-2xl p-4 sm:p-8">
    <div class="pointy-brand font-pixel text-sm text-zinc-500">Pointy<span class="text-indigo-400">_</span></div>
    <h1 class="pointy-title mt-4 text-2xl font-semibold text-zinc-100">{{ $type->name }}</h1>
    <p class="pointy-meta mt-1 text-sm text-zinc-400">{{ $type->duration_minutes }} minutes @if($type->description)· {{ $type->description }} @endif</p>

    <form method="GET" class="pointy-zone mt-6 flex flex-wrap items-center gap-2 text-sm" data-pointy-zone>
        <label for="tz" class="text-zinc-400">Times shown in</label>
        <select name="tz" id="tz" onchange="this.form.submit()" class="rounded-md border border-zinc-700 px-2 py-1 text-sm">@foreach($timezones as $zone)<option value="{{ $zone }}" @selected($zone === $timezone)>{{ str_replace('_', ' ', $zone) }}</option>@endforeach</select>
        <noscript><button class="rounded border px-2 py-1">Update</button></noscript>
    </form>

    @if($days->isEmpty())
        <p class="mt-8 text-sm text-zinc-400">No free times right now. Check back later.</p>
    @else
        <div class="pointy-days mt-5 flex flex-wrap gap-2">
            @foreach($days as $day => $slots)
                @php($label = $slots->first()->format('D j M'))
                <a href="{{ route('pointy.show', ['slug' => $type->slug, 'tz' => $timezone, 'date' => $day]) }}" class="pointy-day {{ $day === $date ? 'pointy-day-active' : '' }} rounded border px-3 py-1.5 text-sm {{ $day === $date ? 'border-indigo-500 bg-indigo-600 text-white' : 'border-zinc-700 hover:bg-zinc-800' }}">{{ $label }}</a>
            @endforeach
        </div>

        <form method="POST" action="{{ route('pointy.book', $type->slug) }}" class="pointy-form mt-6">
            @csrf
            <input type="hidden" name="guest_timezone" value="{{ $timezone }}">
            <input type="hidden" name="guest_hour12" value="0" data-hour12-field>
            <div class="pointy-slots grid grid-cols-3 gap-2 sm:grid-cols-4">
                @foreach($days[$date] as $slot)
                    <label class="cursor-pointer">
                        <input type="radio" name="starts_at" value="{{ $slot->utc()->toIso8601String() }}" required class="peer sr-only" @checked(old('starts_at') === $slot->utc()->toIso8601String())>
                        <span data-local-time="{{ $slot->utc()->toIso8601String() }}" data-zone="{{ $timezone }}" class="pointy-slot block rounded border border-zinc-700 px-2 py-1.5 text-center text-sm peer-checked:border-indigo-500 peer-checked:bg-indigo-600 peer-checked:text-white hover:bg-zinc-800 peer-focus-visible:ring-2">{{ $slot->format('H:i') }}</span>
                    </label>
                @endforeach
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <input name="guest_name" value="{{ old('guest_name') }}" placeholder="Your name" required maxlength="100" aria-label="Your name" class="{{ $field }}">
                <input type="email" name="guest_email" value="{{ old('guest_email') }}" placeholder="Your email" required maxlength="200" aria-label="Your email" class="{{ $field }}">
                <textarea name="notes" rows="3" placeholder="Anything we should know? (optional)" maxlength="1000" aria-label="Notes" class="{{ $field }} sm:col-span-2">{{ old('notes') }}</textarea>
            </div>
            @if($errors->any())<p class="pointy-error mt-3 text-sm text-red-400">{{ $errors->first() }}</p>@endif
            <button class="pointy-submit mt-4 rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Book this time</button>
        </form>
    @endif
</main>
<script>
    (function () {
        try {
            var params = new URLSearchParams(location.search);
            var zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            var select = document.getElementById('tz');
            if (!params.has('tz') && zone && select && Array.prototype.some.call(select.options, function (o) { return o.value === zone; })) {
                select.value = zone;
                select.form.submit();
            }
        } catch (e) {}
    })();
</script>
</body>
</html>
