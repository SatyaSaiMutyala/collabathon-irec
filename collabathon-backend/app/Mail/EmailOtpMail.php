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
        // Queued (see AuthController::deliverEmailOtp()), so this runs inside the
        // queue worker's own process — a separate one from the request that
        // dispatched it, which never inherited the `Config::set()` calls
        // MailSettings::apply() made there. Calling it again here, right before the
        // message is actually built, is what keeps the worker sending through
        // Mailjet instead of quietly falling back to whatever `MAIL_MAILER` is in
        // .env (the `log` driver, in this app — see it never actually leaving).
        MailSettings::apply();

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
