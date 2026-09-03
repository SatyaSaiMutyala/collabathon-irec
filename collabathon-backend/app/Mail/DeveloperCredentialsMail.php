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
 * Sent the moment an admin creates a developer account in the panel.
 *
 * Unlike {@see BrokerApprovedMail}, the password here is never null — developers sign in
 * with email + password (see AuthController::login()'s own docblock), so a plaintext
 * password always exists at creation time, generated or typed by the admin.
 */
class DeveloperCredentialsMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $developer,
        public string $password,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(MailSettings::fromAddress(), MailSettings::fromName()),
            subject: 'Your ' . config('app.name') . ' developer account is ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.developer-credentials',
            with: [
                'name' => $this->developer->name,
                'email' => $this->developer->email,
                'password' => $this->password,
                'company' => $this->developer->developer?->company_name,
                'appName' => config('app.name'),
            ],
        );
    }
}
