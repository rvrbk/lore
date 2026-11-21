<?php

namespace App\Http\Controllers;

use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\BookingType;
use App\Services\BookingNotifier;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PointyController extends Controller
{
    public function index()
    {
        return view('pointy.index', [
            'types' => BookingType::with(['exceptions' => fn ($query) => $query->where('date', '>=', now()->toDateString())->orderBy('date')])->withCount(['bookings' => fn ($query) => $query->active()->where('starts_at', '>=', now())])->orderBy('name')->get(),
            'bookings' => Booking::active()->where('ends_at', '>=', now())->with('type')->orderBy('starts_at')->get(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['name']);
        BookingType::create($data);

        return redirect()->route('pointy.index');
    }

    public function update(Request $request, BookingType $bookingType): RedirectResponse
    {
        $data = $this->validated($request, $bookingType);
        $data['slug'] = $data['slug'] ?? $bookingType->slug;
        $bookingType->update($data);

        return redirect()->route('pointy.index');
    }

    public function destroy(BookingType $bookingType): RedirectResponse
    {
        $bookingType->delete();

        return redirect()->route('pointy.index');
    }

    public function storeException(Request $request, BookingType $bookingType): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_with:start_time', 'date_format:H:i', 'after:start_time'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $bookingType->exceptions()->updateOrCreate(['date' => $data['date']], [
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        return redirect()->route('pointy.index');
    }

    public function destroyException(AvailabilityException $exception): RedirectResponse
    {
        $exception->delete();

        return redirect()->route('pointy.index');
    }

    public function cancel(Booking $booking, BookingNotifier $notifier): RedirectResponse
    {
        if (! $booking->isCancelled()) {
            $booking->cancel();
            $notifier->send($booking, 'cancelled');
        }

        return redirect()->route('pointy.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?BookingType $ignore = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:60', Rule::unique('booking_types', 'slug')->ignore($ignore)],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'min_notice_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'max_days_ahead' => ['required', 'integer', 'min:1', 'max:365'],
            'meet_url' => ['nullable', 'url:https', 'max:255', 'regex:#^https://meet\.google\.com/[a-z0-9-]+(\?.*)?$#i'],
        ], ['slug.regex' => 'Use lowercase letters, numbers and dashes for the link.', 'meet_url.regex' => 'Use a Google Meet link, like https://meet.google.com/abc-defg-hij.']);

        $data['days'] = array_map('intval', $data['days']);
        $data['is_active'] = $request->boolean('is_active');
        $data['include_meet'] = $request->boolean('include_meet');
        if ($data['include_meet'] && blank($data['meet_url'] ?? null) && blank(config('services.pointy.meet_url'))) {
            throw ValidationException::withMessages(['meet_url' => 'Add your Google Meet link, or switch the Meet link off.']);
        }

        return $data;
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'meeting';
        $slug = $base;
        for ($suffix = 2; BookingType::where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
