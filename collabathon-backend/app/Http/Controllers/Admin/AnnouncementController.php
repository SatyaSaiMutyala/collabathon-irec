<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The admin panel's manual push — an announcement to brokers, developers, or both.
 *
 * Separate from the lifecycle triggers in PushNotifier because it is the one send with
 * no domain event behind it: an admin typed something and chose an audience. Everything
 * else fires from a state change and needs no UI at all.
 */
class AnnouncementController extends Controller
{
    /** Anything larger than this is a campaign, not an announcement — see the note below. */
    private const MAX_RECIPIENTS = 500;

    public function store(Request $request, PushNotifier $push): RedirectResponse
    {
        // Reuses the settings permission rather than inventing a module: broadcasting to
        // the whole platform is an operator action, not a per-record one.
        $this->authorize('edit-module', 'settings');

        $data = $request->validate([
            'audience' => ['required', 'in:brokers,developers,everyone'],
            'title' => ['required', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:180'],
        ], [
            'title.max' => 'Keep the title under 60 characters — Android truncates past that.',
            'body.max' => 'Keep the message under 180 characters so it is readable unexpanded.',
        ]);

        $roles = match ($data['audience']) {
            'brokers' => [User::ROLE_BROKER],
            'developers' => [User::ROLE_DEVELOPER],
            default => [User::ROLE_BROKER, User::ROLE_DEVELOPER],
        };

        // Only accounts that can actually sign in. Notifying a rejected broker that a new
        // project is live is worse than not notifying them.
        $recipients = User::query()
            ->whereIn('role', $roles)
            ->where('status', User::STATUS_ACTIVE)
            // Skipping anyone with no device saves a wasted row in the token lookup and
            // keeps the cap meaningful — it counts reachable people, not accounts.
            ->whereHas('deviceTokens')
            ->limit(self::MAX_RECIPIENTS + 1)
            ->get(['id']);

        if ($recipients->count() > self::MAX_RECIPIENTS) {
            return back()->with('error', sprintf(
                'That reaches more than %d devices. Sends run inline on this request, so a '
                . 'broadcast that size needs a queue worker before it will finish — narrow '
                . 'the audience or ask for the queued version.',
                self::MAX_RECIPIENTS,
            ));
        }

        if ($recipients->isEmpty()) {
            return back()->with('warning', 'Nobody in that audience has the app installed yet.');
        }

        $result = $push->custom($recipients, $data['title'], $data['body']);

        return back()->with('success', sprintf(
            'Sent to %d device%s.%s',
            $result['sent'],
            $result['sent'] === 1 ? '' : 's',
            $result['failed'] > 0 ? " {$result['failed']} could not be delivered." : '',
        ));
    }
}
