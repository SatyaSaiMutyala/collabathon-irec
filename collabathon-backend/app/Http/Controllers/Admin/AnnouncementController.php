<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PushNotifier;
use App\Support\FirebaseCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The admin panel's manual push — an announcement to brokers, developers, or both.
 *
 * Separate from the lifecycle triggers in PushNotifier because it is the one send with
 * no domain event behind it: an admin typed something and chose an audience. Everything
 * else fires from a state change and needs no UI at all.
 *
 * Its own sidebar page rather than a tab buried in Settings — sending one of these is
 * something an admin actually does, day to day, not a piece of infrastructure to
 * configure once. The Firebase service account itself (the credential this depends on)
 * stays under Settings > Access, where every other third-party credential on this
 * panel already lives.
 */
class AnnouncementController extends Controller
{
    /** Anything larger than this is a campaign, not an announcement — see the note below. */
    private const MAX_RECIPIENTS = 500;

    /**
     * `'push-notifications'` is not (yet) a real Role::MODULES entry — Gate::before
     * lets the single Super Admin account straight through regardless, which is the
     * only account that exists today. Register it there (plus the matching
     * role_permissions seed) the day a non-super-admin Team member actually needs
     * this page; nothing here would need to change, the string already matches.
     */
    public function index(): View
    {
        $this->authorize('view-module', 'push-notifications');

        // What "Send" would actually reach right now, before anyone types anything —
        // the whole point of asking is to know in advance whether an audience is even
        // reachable, rather than finding out from a "Sent to 0 devices" message after.
        $reachable = [
            'brokers' => User::role(User::ROLE_BROKER)
                ->where('status', User::STATUS_ACTIVE)->whereHas('deviceTokens')->count(),
            'developers' => User::role(User::ROLE_DEVELOPER)
                ->where('status', User::STATUS_ACTIVE)->whereHas('deviceTokens')->count(),
        ];

        return view('admin.push-notifications', [
            'firebaseConfigured' => FirebaseCredentials::isConfigured(),
            'reachable' => $reachable,
        ]);
    }

    // See the matching note on SettingsController::settingsResponse() — a real
    // redirect for a normal submit, JSON for the settings page's own fetch-based one.
    private function respond(Request $request, string $message, string $flash = 'success'): RedirectResponse|JsonResponse
    {
        if ($request->ajax()) {
            return response()->json(['message' => $message]);
        }

        return back()->with($flash, $message);
    }

    public function store(Request $request, PushNotifier $push): RedirectResponse|JsonResponse
    {
        $this->authorize('edit-module', 'push-notifications');

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
            return $this->respond($request, sprintf(
                'That reaches more than %d devices. Sends run inline on this request, so a '
                . 'broadcast that size needs a queue worker before it will finish — narrow '
                . 'the audience or ask for the queued version.',
                self::MAX_RECIPIENTS,
            ), 'error');
        }

        if ($recipients->isEmpty()) {
            return $this->respond($request, 'Nobody in that audience has the app installed yet.', 'warning');
        }

        $result = $push->custom($recipients, $data['title'], $data['body']);

        return $this->respond($request, sprintf(
            'Sent to %d device%s.%s',
            $result['sent'],
            $result['sent'] === 1 ? '' : 's',
            $result['failed'] > 0 ? " {$result['failed']} could not be delivered." : '',
        ));
    }
}
