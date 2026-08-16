<?php

namespace App\Mail;

use App\Models\PasswordResetOtp;
use App\Support\MailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The admin-panel password reset code — see PasswordResetController::sendCode(). Sent
 * through whichever mailer MailSettings::apply() points at (Mailjet, once configured in
 * Admin → Settings); the caller checks the return of apply() first and skips sending
 * rather than letting this throw, same pattern as EmailOtpMail.
 */
class PasswordResetOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public string $code, public string $name)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(MailSettings::fromAddress(), MailSettings::fromName()),
            subject: "Your password reset code: {$this->code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.password-reset-otp',
            with: [
                'code' => $this->code,
                'name' => $this->name,
                'minutes' => PasswordResetOtp::TTL_MINUTES,
                'appName' => config('app.name'),
            ],
        );
    }
}
