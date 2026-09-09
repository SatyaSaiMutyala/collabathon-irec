<?php

namespace App\Services;

use App\Mail\ProjectAssignedMail;
use App\Models\Property;
use App\Support\MailSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Tells a developer a listing has been put under their account — a push to their
 * device and the "New project for your review" email carrying the signed accept and
 * decline links.
 *
 * Lifted out of Admin\PropertyController so the Master Data importer sends exactly
 * what the intake form sends. Two copies of this would drift, and the half that drifts
 * is always the accept/decline link: those URLs are the developer's only way to take
 * ownership of a project without signing in, and a listing whose developer never got
 * them stays invisible to brokers forever, because Property::isVisibleToBrokers()
 * needs their acceptance as well as the admin's publish.
 */
class ProjectAssignmentNotifier
{
    /**
     * How long the emailed accept/decline links stay valid. Long enough for a developer
     * who reads their email weekly; short enough that a forwarded mail is not a
     * standing key to the account.
     */
    private const LINK_LIFETIME_DAYS = 14;

    public function __construct(private readonly PushNotifier $push) {}

    /**
     * Both channels, neither of them able to fail the caller: the listing is already
     * saved by the time this runs, and a mail server that is down must not turn a
     * successful save into an error page. Delivery problems go to the log.
     */
    public function assigned(Property $property): void
    {
        $this->push->propertyAssigned($property);
        $this->email($property);
    }

    private function email(Property $property): bool
    {
        if (! MailSettings::apply()) {
            return false;
        }

        $developer = $property->developer ?? $property->loadMissing('developer')->developer;

        if (! $developer?->email) {
            return false;
        }

        try {
            $property->loadMissing(['detail', 'unitTypes']);

            $expires = now()->addDays(self::LINK_LIFETIME_DAYS);

            $acceptUrl = URL::temporarySignedRoute('developer-response.show', $expires, [
                'property' => $property->id,
                'action' => 'accept',
            ]);
            $declineUrl = URL::temporarySignedRoute('developer-response.show', $expires, [
                'property' => $property->id,
                'action' => 'decline',
            ]);

            Mail::to($developer->email)->send(new ProjectAssignedMail($property, $acceptUrl, $declineUrl));

            return true;
        } catch (\Throwable $e) {
            Log::error('Project assignment email failed', [
                'property_id' => $property->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
