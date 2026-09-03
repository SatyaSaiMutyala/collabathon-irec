<?php

namespace App\Mail;

use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent the moment an admin rejects a broker's registration, or revokes a previously
 * approved broker's access — the other outcome of the same decision point as
 * {@see BrokerApprovedMail}. See ApprovalController::reject().
 *
 * `$wasRevoked` picks the copy: a registration that was never approved reads
 * differently from access that worked yesterday and doesn't today.
 */
class BrokerRejectedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $broker,
        public string $reason,
        public bool $wasRevoked = false,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(MailSettings::fromAddress(), MailSettings::fromName()),
            subject: $this->wasRevoked
                ? 'Your ' . config('app.name') . ' access has been paused'
                : 'Update on your ' . config('app.name') . ' registration',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.broker-rejected',
            with: [
                'name' => $this->broker->name,
                'reason' => $this->reason,
                'wasRevoked' => $this->wasRevoked,
                'appName' => config('app.name'),
            ],
        );
    }
}
