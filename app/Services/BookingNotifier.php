<?php

namespace App\Services;

use App\Mail\BookingMail;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends Pointy's emails (from pointy@verbeek.ug) to the guest and the owner, and a Telegram alert to the owner for new bookings. A failure never blocks a booking.
 */
class BookingNotifier
{
    public function __construct(private Telegram $telegram) {}

    public function send(Booking $booking, string $event): void
    {
        $booking->loadMissing(['type', 'user']);
        if ($event === 'confirmed') {
            $this->alertOwner($booking);
        }
        $recipients = [[$booking->guest_email, false]];
        if (filled(config('services.pointy.owner_email'))) {
            $recipients[] = [config('services.pointy.owner_email'), true];
        }

        foreach ($recipients as [$address, $forOwner]) {
            try {
                Mail::to($address)->send(new BookingMail($booking, $event, $forOwner));
            } catch (Throwable $exception) {
                Log::warning('Pointy email failed: '.$exception->getMessage());
            }
        }
    }

    private function alertOwner(Booking $booking): void
    {
        $timezone = $booking->type->timezone;

        $when = $booking->starts_at->copy()->setTimezone($timezone)->format('D j M, H:i').' ('.str_replace('_', ' ', $timezone).')';

        $this->telegram->sendTo($booking->user, "📅 <b>New booking</b>\n".e($booking->type->name).' with '.e($booking->guest_name).' ('.e($booking->guest_email).")\n".e($when).($booking->notes ? "\n".e($booking->notes) : ''));
    }
}
