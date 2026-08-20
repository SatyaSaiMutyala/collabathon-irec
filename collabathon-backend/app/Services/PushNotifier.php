<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Every push the platform sends, one method per domain event.
 *
 * Call sites say what happened — `$push->requestAccepted($lead)` — never what the copy
 * is or who receives it. That keeps the wording in one file instead of scattered across
 * two controllers and a Blade view, and it means the recipient rule for an event lives
 * next to the event rather than being re-derived at each caller.
 *
 * Nothing here throws. A push that fails must not roll back the approval or the lead
 * decision that triggered it — the notification is a courtesy, the state change is the
 * point.
 */
class PushNotifier
{
    public function __construct(private readonly Fcm $fcm)
    {
    }

    // ------------------------------------------------------------------ broker lifecycle

    /** A broker submitted a registration — every admin who can act on it is told. */
    public function brokerRegistered(User $broker): void
    {
        $this->toAdmins(
            'New broker registration',
            "{$broker->name} has applied and is awaiting review.",
            ['type' => 'broker_registered', 'broker_id' => $broker->id],
        );
    }

    public function brokerApproved(User $broker): void
    {
        $this->toUser(
            $broker,
            'You are approved',
            'Your registration was approved — sign in to start browsing inventory.',
            ['type' => 'broker_approved'],
        );
    }

    public function brokerRejected(User $broker): void
    {
        $this->toUser(
            $broker,
            'Registration not approved',
            'An admin reviewed your registration and could not approve it.',
            ['type' => 'broker_rejected'],
        );
    }

    /** Access revoked or paused after the fact — the app will sign them out on next call. */
    public function brokerAccessRevoked(User $broker): void
    {
        $this->toUser(
            $broker,
            'Access paused',
            'Your access has been paused. Contact the Collabathon team for details.',
            ['type' => 'broker_revoked'],
        );
    }

    public function passwordReset(User $user): void
    {
        $this->toUser(
            $user,
            'Your password was changed',
            'An admin issued a new password for your account. You have been signed out.',
            ['type' => 'password_reset'],
        );
    }

    // ------------------------------------------------------------------ lead lifecycle

    /** A broker asked about a listing — the developer who owns it is told. */
    public function requestReceived(Lead $lead): void
    {
        $lead->loadMissing(['broker:id,name', 'property:id,name', 'developer:id,user_id']);

        $owner = $lead->developer?->user_id ? User::find($lead->developer->user_id) : null;
        if ($owner === null) {
            return;
        }

        $this->toUser(
            $owner,
            'New broker request',
            sprintf(
                '%s sent a request for %s.',
                $lead->broker?->name ?? 'A broker',
                $lead->property?->name ?? 'one of your listings',
            ),
            ['type' => 'request_received', 'lead_id' => $lead->id],
        );
    }

    public function requestAccepted(Lead $lead): void
    {
        $lead->loadMissing(['broker:id,name', 'property:id,name', 'developer:id,company_name']);

        $this->toUser(
            $lead->broker,
            'Request accepted',
            sprintf(
                '%s accepted your request for %s. Their contact details are now visible.',
                $lead->developer?->company_name ?? 'The developer',
                $lead->property?->name ?? 'the listing',
            ),
            ['type' => 'request_accepted', 'lead_id' => $lead->id],
        );
    }

    public function requestDeclined(Lead $lead): void
    {
        $lead->loadMissing(['property:id,name', 'developer:id,company_name']);

        $this->toUser(
            $lead->broker,
            'Request declined',
            sprintf(
                '%s passed on your request for %s.',
                $lead->developer?->company_name ?? 'The developer',
                $lead->property?->name ?? 'the listing',
            ),
            ['type' => 'request_declined', 'lead_id' => $lead->id],
        );
    }

    // ------------------------------------------------------------------ inventory

    /** A listing was assigned to a developer, or its details changed materially. */
    public function propertyAssigned(Property $property): void
    {
        $property->loadMissing('developer:id,user_id');

        $owner = $property->developer?->user_id ? User::find($property->developer->user_id) : null;
        if ($owner === null) {
            return;
        }

        $this->toUser(
            $owner,
            'New listing assigned',
            "{$property->name} is now live under your account.",
            ['type' => 'property_assigned', 'property_id' => $property->id],
        );
    }

    // ------------------------------------------------------------------ manual

    /** The admin panel's free-text send. Recipients are already resolved by the caller. */
    public function custom(Collection $users, string $title, string $body): array
    {
        return $this->dispatch(
            $this->tokensFor($users->pluck('id')->all()),
            $title,
            $body,
            ['type' => 'announcement'],
        );
    }

    // ------------------------------------------------------------------ plumbing

    private function toUser(?User $user, string $title, string $body, array $data): void
    {
        if ($user === null) {
            return;
        }

        $this->dispatch($this->tokensFor([$user->id]), $title, $body, $data);
    }

    /**
     * Admins are a small, bounded set — every active admin account, not a topic. A topic
     * would mean an admin who lost the permission keeps receiving, because unsubscribing
     * happens on the device and we cannot make it happen from here.
     */
    private function toAdmins(string $title, string $body, array $data): void
    {
        $adminIds = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where('status', User::STATUS_ACTIVE)
            ->pluck('id')
            ->all();

        $this->dispatch($this->tokensFor($adminIds), $title, $body, $data);
    }

    /** @param  array<int,int>  $userIds  @return array<int,string> */
    private function tokensFor(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return DeviceToken::whereIn('user_id', $userIds)->pluck('token')->all();
    }

    /**
     * The one place a send happens, so pruning dead tokens is not something each caller
     * has to remember. An uninstalled app leaves its token behind forever otherwise, and
     * every later push pays for it.
     */
    private function dispatch(array $tokens, string $title, string $body, array $data): array
    {
        $result = $this->fcm->send($tokens, $title, $body, $data);

        if ($result['invalid'] !== []) {
            DeviceToken::whereIn('token', $result['invalid'])->delete();
        }

        return $result;
    }
}
