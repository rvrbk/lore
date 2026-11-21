<?php

namespace App\Mail;

use App\Models\BookingType;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Pointy's booking request: invites someone to pick a time through a booking type's public link.
 */
class BookingRequestMail extends Mailable
{
    public function __construct(public BookingType $type, public ?string $guestName, public ?string $note) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('services.pointy.from'), config('services.pointy.from_name')),
            subject: "Pick a time: {$this->type->name}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.booking-request', with: ['url' => route('pointy.show', $this->type->slug)]);
    }
}
