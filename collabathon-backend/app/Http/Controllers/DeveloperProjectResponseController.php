<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Support\PropertyDeveloperResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public (signed-URL, no login) side of a developer's accept/decline — reached from
 * the "New project for your review" email, never from the app.
 *
 * A GET always renders a confirmation step first rather than acting immediately: mail
 * clients and corporate link-scanners (Outlook Safe Links, Gmail image proxying, etc.)
 * pre-fetch GET links to check them for safety, which would silently accept or decline a
 * project nobody actually clicked. The state only changes on POST, which scanners don't
 * replay.
 *
 * Sits outside the `Admin` namespace and the `auth` middleware on purpose — the developer
 * has no admin session, often no session at all — the signature on the URL is the only
 * credential, scoped to this one property and expiring on its own.
 */
class DeveloperProjectResponseController extends Controller
{
    public function show(Request $request, Property $property): View
    {
        $action = $this->action($request);
        $property->loadMissing('developer');

        return view('developer-response.confirm', [
            'property' => $property,
            'action' => $action,
            // The POST target: the exact same signed URL, so hasValidSignature() sees an
            // identical path+query on the way back in and needs no route-name coupling.
            'actionUrl' => $request->fullUrl(),
            'alreadyRespondedAs' => $property->developer_status !== Property::DEV_PENDING
                ? $property->developer_status
                : null,
        ]);
    }

    public function store(Request $request, Property $property): View
    {
        $action = $this->action($request);

        $reason = null;
        if ($action === 'decline') {
            $reason = $request->validate([
                'reason' => ['required', 'string', 'max:2000'],
            ])['reason'];
        }

        PropertyDeveloperResponse::apply(
            $property,
            $action === 'accept' ? Property::DEV_ACCEPTED : Property::DEV_DECLINED,
            $reason,
        );

        return view('developer-response.result', [
            'property' => $property,
            'status' => $action === 'accept' ? Property::DEV_ACCEPTED : Property::DEV_DECLINED,
        ]);
    }

    /** The link only ever carries 'accept' or 'decline' — anything else means a tampered URL. */
    private function action(Request $request): string
    {
        $action = $request->query('action');
        abort_unless(in_array($action, ['accept', 'decline'], true), 404);

        return $action;
    }
}
