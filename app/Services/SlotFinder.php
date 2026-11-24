<?php

namespace App\Services;

use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\BookingType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Works out which start times of a booking type are still free. The weekly hours (general, or per weekday) and per-date exceptions to them are in the owner's time zone,
 * the results are UTC instants so they can be shown in any guest time zone.
 */
class SlotFinder
{
    /**
     * @return Collection<int, CarbonImmutable>
     */
    public function free(BookingType $type): Collection
    {
        $now = CarbonImmutable::now('UTC');
        $earliest = $now->addHours($type->min_notice_hours);
        $latest = $now->addDays($type->max_days_ahead);
        $buffer = $type->buffer_minutes;

        $booked = Booking::withoutGlobalScopes()->active()
            ->where('booking_type_id', $type->id)
            ->where('ends_at', '>', now()->subDay())
            ->get(['starts_at', 'ends_at']);

        $exceptions = $type->exceptions()->withoutGlobalScopes()->get()->keyBy(fn (AvailabilityException $exception) => $exception->date->format('Y-m-d'));

        $slots = collect();
        $day = $now->setTimezone($type->timezone)->startOfDay()->subDay();
        $lastDay = $latest->setTimezone($type->timezone)->startOfDay()->addDay();

        for (; $day <= $lastDay; $day = $day->addDay()->startOfDay()) {
            $exception = $exceptions->get($day->format('Y-m-d'));
            if ($exception?->isClosed() || (! $exception && ! in_array($day->dayOfWeekIso, $type->days, true))) {
                continue;
            }

            $hours = $type->hoursOnWeekday($day->dayOfWeekIso);
            $end = $day->setTimeFromTimeString($exception->end_time ?? $hours['end_time']);
            for ($start = $day->setTimeFromTimeString($exception->start_time ?? $hours['start_time']); $start->addMinutes($type->duration_minutes) <= $end; $start = $start->addMinutes($type->duration_minutes + $buffer)) {
                $slot = $start->setTimezone('UTC');
                if ($slot < $earliest || $slot > $latest) {
                    continue;
                }
                $slotEnd = $slot->addMinutes($type->duration_minutes + $buffer);
                $taken = $booked->contains(fn (Booking $booking) => $booking->starts_at < $slotEnd && $booking->ends_at->copy()->addMinutes($buffer) > $slot);
                if (! $taken) {
                    $slots->push($slot);
                }
            }
        }

        return $slots->unique(fn (CarbonImmutable $slot) => $slot->getTimestamp())->values();
    }

    /**
     * Free slots grouped by the calendar day they fall on in the guest's time zone.
     *
     * @return Collection<string, Collection<int, CarbonImmutable>>
     */
    public function freeByDay(BookingType $type, string $guestTimezone): Collection
    {
        return $this->free($type)
            ->map(fn (CarbonImmutable $slot) => $slot->setTimezone($guestTimezone))
            ->groupBy(fn (CarbonImmutable $slot) => $slot->format('Y-m-d'));
    }
}
