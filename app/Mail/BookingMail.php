<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Pointy's confirmation / cancellation email, for the guest or for the owner, with times in that person's time zone.
 */
class BookingMail extends Mailable
{
    public function __construct(public Booking $booking, public string $event, public bool $forOwner) {}

    public function timezone(): string
    {
        return $this->forOwner ? $this->booking->type->timezone : $this->booking->guest_timezone;
    }

    public function whenText(): string
    {
        $start = $this->booking->starts_at->copy()->setTimezone($this->timezone());
        $end = $this->booking->ends_at->copy()->setTimezone($this->timezone());

        $pattern = Setting::timePattern();

        return $start->format('l j F Y, '.$pattern).' – '.$end->format($pattern).' ('.str_replace('_', ' ', $this->timezone()).')';
    }

    public function envelope(): Envelope
    {
        $verb = $this->event === 'cancelled' ? 'Cancelled' : 'Confirmed';
        $subject = $this->forOwner
            ? "{$verb}: {$this->booking->type->name} with {$this->booking->guest_name}"
            : "{$verb}: {$this->booking->type->name}";

        return new Envelope(
            from: new Address(config('services.pointy.from'), config('services.pointy.from_name')),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.booking', with: ['when' => $this->whenText()]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if ($this->event === 'cancelled') {
            return [];
        }

        return [Attachment::fromData(fn () => $this->calendarInvite(), 'meeting.ics')->withMime('text/calendar')];
    }

    private function calendarInvite(): string
    {
        $stamp = fn ($moment) => $moment->copy()->setTimezone('UTC')->format('Ymd\THis\Z');
        $summary = str_replace([',', ';', "\n"], ['\,', '\;', ' '], $this->booking->type->name.' with '.($this->forOwner ? $this->booking->guest_name : config('services.pointy.from_name')));

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Pointy//Lore//EN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:{$this->booking->token}@pointy",
            'DTSTAMP:'.$stamp(now()),
            'DTSTART:'.$stamp($this->booking->starts_at),
            'DTEND:'.$stamp($this->booking->ends_at),
            "SUMMARY:{$summary}",
            'END:VEVENT',
            'END:VCALENDAR',
        ])."\r\n";
    }
}
