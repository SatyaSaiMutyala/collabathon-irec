<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The broadcasts an admin sent from Push notifications, for the app's Notifications
 * screen.
 *
 * These used to exist only as an FCM payload, which meant a push that arrived while
 * the phone was locked — or was swiped away from the shade — was gone for good: the
 * Notifications screen is built by useNotifications from the caller's *leads*, and a
 * broadcast is not a lead, so it had nowhere to appear. Announcement rows are already
 * persisted for the admin-side history, so serving that same table here is what makes
 * the screen agree with what was actually sent.
 *
 * Deliberately not a per-user pivot table: `announcements.audience` plus the caller's
 * role is enough to answer "was this for me", and a broadcast to every user would
 * otherwise write a row per recipient to store nothing that isn't already derivable.
 * The cost is that read/unread stays client-side (notificationsSlice.readIds), which
 * is where it already lived for the lead-derived entries.
 */
class NotificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $audiences = match ($user->role) {
            User::ROLE_BROKER => ['brokers', 'everyone'],
            User::ROLE_DEVELOPER => ['developers', 'everyone'],
            // An admin signing in through the app has no broadcast audience of their
            // own. Empty rather than everything: this is the caller's inbox, not a log.
            default => [],
        };

        $announcements = Announcement::query()
            ->whereIn('audience', $audiences)
            // Someone who signed up today has no business being shown last month's
            // broadcast as though they had missed it.
            ->where('created_at', '>=', $user->created_at)
            ->latest()
            // The screen is a feed, not an archive, and it renders the whole list
            // without pagination — so bound it here rather than growing forever.
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $announcements->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'image_url' => $a->imageUrl(),
                'created_at' => $a->created_at->toIso8601String(),
            ]),
        ]);
    }
}
