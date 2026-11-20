<?php

namespace App\Services;

use App\Mail\BookingMail;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends Pointy's emails (from pointy@verbeek.ug) to the guest and the owner. A mail failure never blocks a booking.
 */
class BookingNotifier
{
    public function send(Booking $booking, string $event): void
    {
        $booking->loadMissing('type');
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
}
