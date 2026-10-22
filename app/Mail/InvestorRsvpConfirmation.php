<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tackmejl till den som anmält sig (eller tackat nej) till investerarträffen.
 * Skickas från info@oqto.se, och svar går dit.
 */
class InvestorRsvpConfirmation extends Mailable
{
    public const FROM_ADDRESS = 'info@oqto.se';

    public const FROM_NAME = 'Oqto';

    /**
     * @param  array<string, mixed>  $details  Anmälan (attending, joins_dinner, ...) samt eventfält från sidan
     */
    public function __construct(
        public string $recipientName,
        public bool $attending,
        public ?string $invitedBy,
        public array $details,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(self::FROM_ADDRESS, self::FROM_NAME),
            replyTo: [new Address(self::FROM_ADDRESS, self::FROM_NAME)],
            subject: $this->attending
                ? 'Tack för din anmälan till Investerarträff för Oqto'
                : 'Tack för ditt svar – Investerarträff för Oqto',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.investor-rsvp-confirmation',
            text: 'mail.investor-rsvp-confirmation-text',
        );
    }
}
