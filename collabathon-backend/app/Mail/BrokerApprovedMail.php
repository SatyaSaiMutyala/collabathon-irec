<?php

namespace App\Mail;

use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent the moment an admin approves a broker's registration.
 *
 * Carries the sign-in details, because "you have been approved" without them just moves
 * the question to "approved to do what, where?".
 *
 * `$password` is only set when the admin issued one — on a plain approval the broker signs
 * in with the password they chose at registration, and this mailable says so rather than
 * inventing a credential nobody can use.
 */
class BrokerApprovedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $broker,
        public ?string $password = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                MailSettings::fromAddress(),
                MailSettings::fromName(),
            ),
            subject: 'Your ' . config('app.name') . ' account is approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.broker-approved',
            with: [
                'name' => $this->broker->name,
                'email' => $this->broker->email,
                'password' => $this->password,
                'company' => $this->broker->brokerProfile?->company_name,
                'appName' => config('app.name'),
            ],
        );
    }
}
