<?php

namespace App\Mail;

use App\Models\Lead;
use App\Support\MailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the developer the moment a broker expresses interest in one of their
 * listings — the email counterpart to PushNotifier::requestReceived(), for a developer
 * who isn't watching the app right then. Carries just enough to act on: which broker,
 * which listing — never the broker's still-locked contact details, which stay hidden
 * until the developer accepts inside the app.
 */
class LeadInterestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Lead $lead)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(MailSettings::fromAddress(), MailSettings::fromName()),
            subject: 'New broker request — ' . ($this->lead->property?->name ?? 'your listing'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.lead-interest',
            with: [
                'developerName' => $this->lead->developer?->contact_person,
                'brokerName' => $this->lead->broker?->name ?? 'A broker',
                'propertyName' => $this->lead->property?->name ?? 'one of your listings',
                'appName' => config('app.name'),
            ],
        );
    }
}
