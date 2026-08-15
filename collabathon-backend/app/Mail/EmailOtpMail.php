<?php

namespace App\Mail;

use App\Models\EmailOtpCode;
use App\Support\MailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The channel-partner sign-in code — see AuthController::sendEmailOtp(). Sent through
 * whichever mailer MailSettings::apply() points at (Mailjet, once configured in Admin
 * → Settings); the caller checks MailSettings::isConfigured() first and skips sending
 * rather than letting this throw, same pattern as BrokerApprovedMail.
 */
class EmailOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public string $code)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(MailSettings::fromAddress(), MailSettings::fromName()),
            subject: "Your sign-in code: {$this->code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.email-otp',
            with: [
                'code' => $this->code,
                'minutes' => EmailOtpCode::TTL_MINUTES,
                'appName' => config('app.name'),
            ],
        );
    }
}
