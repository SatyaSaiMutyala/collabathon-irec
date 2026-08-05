<?php

namespace App\Mail;

use App\Models\Property;
use App\Support\MailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent the moment an admin publishes a project under a developer's account.
 *
 * Carries the full project sheet — not just a "you have a new listing" ping — so the
 * developer can decide from the inbox alone. The two action links are signed, expiring
 * URLs (see routes/web.php + DeveloperProjectResponseController): clicking one lets the
 * developer accept or decline without signing in anywhere, and both land on the exact
 * same `PropertyDeveloperResponse::apply()` write the in-app response uses, so whichever
 * channel they answer from, the state — and what a channel partner sees — is identical.
 */
class ProjectAssignedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Property $property,
        public string $acceptUrl,
        public string $declineUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(MailSettings::fromAddress(), MailSettings::fromName()),
            subject: 'New project for your review — ' . $this->property->name,
        );
    }

    public function content(): Content
    {
        $property = $this->property;
        $detail = $property->detail;

        return new Content(
            view: 'mail.project-assigned',
            with: [
                'property' => $property,
                'detail' => $detail,
                'unitTypes' => $property->unitTypes,
                'acceptUrl' => $this->acceptUrl,
                'declineUrl' => $this->declineUrl,
                'appName' => config('app.name'),
                'coverImageUrl' => $property->cover_image_path ? asset('storage/' . $property->cover_image_path) : null,
            ],
        );
    }
}
