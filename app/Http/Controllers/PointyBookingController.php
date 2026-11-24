<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingType;
use App\Models\PointySite;
use App\Services\BookingNotifier;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The public side of Pointy: guests pick a free slot in their own time zone, book it and can cancel it with their link.
 */
class PointyBookingController extends Controller
{
    public function show(Request $request, string $slug, SlotFinder $finder)
    {
        $type = BookingType::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $timezone = $this->timezone($request->query('tz'), $type->timezone);
        $days = $finder->freeByDay($type, $timezone);
        $date = $days->has($request->query('date')) ? $request->query('date') : $days->keys()->first();

        return view('pointy.show', [
            'type' => $type,
            'timezone' => $timezone,
            'days' => $days,
            'date' => $date,
            'timezones' => DateTimeZone::listIdentifiers(),
            'site' => $this->site($request->query('site')),
        ]);
    }

    public function book(Request $request, string $slug, SlotFinder $finder, BookingNotifier $notifier): RedirectResponse
    {
        $type = BookingType::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'guest_name' => ['required', 'string', 'max:100'],
            'guest_email' => ['required', 'email', 'max:200'],
            'guest_timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'guest_hour12' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'site' => ['nullable', 'string', 'max:60'],
        ]);
        $site = $this->site($data['site'] ?? null);

        $start = CarbonImmutable::parse($data['starts_at'])->setTimezone('UTC');

        $booking = DB::transaction(function () use ($type, $finder, $start, $data, $site) {
            if (! $finder->free($type)->contains(fn (CarbonImmutable $slot) => $slot->equalTo($start))) {
                throw ValidationException::withMessages(['starts_at' => 'That time was just taken. Pick another one.']);
            }

            return Booking::create([
                'user_id' => $type->user_id,
                'booking_type_id' => $type->id,
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'],
                'guest_timezone' => $data['guest_timezone'],
                'meeting_url' => $type->meetingUrl(),
                'guest_hour12' => (bool) ($data['guest_hour12'] ?? false),
                'notes' => $data['notes'] ?? null,
                'starts_at' => $start,
                'ends_at' => $start->addMinutes($type->duration_minutes),
                'token' => Str::random(40),
                'pointy_site_id' => $site?->id,
            ]);
        });

        $booking->scheduleReminder();
        $notifier->send($booking, 'confirmed');

        return redirect()->route('pointy.booking', $booking->token);
    }

    public function booking(string $token)
    {
        $booking = Booking::where('token', $token)->with(['type', 'site'])->firstOrFail();

        return view('pointy.booking', ['booking' => $booking]);
    }

    public function cancel(string $token, BookingNotifier $notifier): RedirectResponse
    {
        $booking = Booking::where('token', $token)->firstOrFail();
        if (! $booking->isCancelled() && $booking->starts_at->isFuture()) {
            $booking->cancel();
            $notifier->send($booking, 'cancelled');
        }

        return redirect()->route('pointy.booking', $token);
    }

    private function site(mixed $key): ?PointySite
    {
        return is_string($key) && $key !== '' ? PointySite::where('key', $key)->first() : null;
    }

    private function timezone(mixed $requested, string $fallback): string
    {
        return is_string($requested) && in_array($requested, DateTimeZone::listIdentifiers(), true) ? $requested : $fallback;
    }
}
