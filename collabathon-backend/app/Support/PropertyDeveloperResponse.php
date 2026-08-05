<?php

namespace App\Support;

use App\Models\Property;

/**
 * The one place that knows how a developer's accept/decline is written to a property.
 *
 * Two call sites reach this: the developer mobile app (`MyPropertyController::respond`,
 * authenticated) and the email accept/decline link (`DeveloperProjectResponseController`,
 * unauthenticated but signed). Both must leave the row in exactly the same state, so the
 * write lives here once instead of being re-implemented per channel and risking the two
 * drifting apart.
 */
class PropertyDeveloperResponse
{
    public static function apply(Property $property, string $status, ?string $reason = null): void
    {
        $property->forceFill([
            'developer_status' => $status,
            'developer_responded_at' => now(),
            // Clearing on accept matters: a developer who declines, then accepts, must
            // not leave a stale rejection reason attached to a live listing.
            'developer_decline_reason' => $status === Property::DEV_DECLINED ? $reason : null,
        ])->save();
    }
}
