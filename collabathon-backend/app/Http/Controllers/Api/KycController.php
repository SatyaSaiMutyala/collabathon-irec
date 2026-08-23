<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GstVerificationService;
use App\Services\PanVerificationService;
use App\Support\SurepassSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Document verification for the Complete Profile screen — PAN and GST, via
 * Surepass. Public, not `auth:sanctum`: this runs mid-registration, before a
 * channel partner has an account or a token yet.
 *
 * Aadhaar verification (photo/XML/eAadhaar PDF) used to live here too, but Surepass's
 * Aadhaar scope was never actually enabled on this account — every attempt answered
 * "Your access token is not valid for this API/Scope", so it was removed rather than
 * left as a KYC step that always errors. Aadhaar is now a plain number + attachment
 * on the registration form, same as RERA, with no verification call at all.
 *
 * Every method here answers with a 200 and a `status` field even when verification
 * itself failed — a bad number or an unreachable Surepass must not surface as a
 * 422/500 that blocks the form. Registration always proceeds either way;
 * verification is a confidence signal on top of it, not a gate in front of it (see
 * docs/KYC_VERIFICATION_PROVIDER_SETUP.md for why: Surepass has no uptime guarantee
 * this app controls, so a hard gate here would let a third party's outage stop
 * channel partners from signing up at all).
 */
class KycController extends Controller
{
    /**
     * POST /api/v1/kyc/pan/verify — a PAN number in, Surepass's PAN Comprehensive
     * data out (name, DOB, gender, address, masked Aadhaar). Never a 422/500 that
     * would stop the form — see the class docblock.
     */
    public function verifyPan(Request $request, PanVerificationService $service): JsonResponse
    {
        $data = $request->validate([
            'pan_number' => ['required', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
        ]);

        if (! SurepassSettings::isConfigured()) {
            // This branch returns before ever reaching PanVerificationService, so
            // without this it leaves zero trace of having fired at all — logged
            // here specifically because it was seen happening intermittently with
            // no way to tell whether that meant a real misconfiguration or a
            // transient DB/decrypt blip reading the stored token.
            Log::warning('PAN verification skipped — Surepass not configured', [
                'environment' => SurepassSettings::environment(),
            ]);

            return response()->json([
                'status' => 'unavailable',
                'message' => 'PAN verification is not configured yet. You can continue — this can be verified later.',
            ]);
        }

        $result = $service->verifyComprehensive($data['pan_number']);

        return response()->json($result);
    }

    /**
     * POST /api/v1/kyc/gst/verify — a GSTIN in, Surepass's GST Advance data out
     * (legal name, registration date, GSTIN status, taxpayer type, address,
     * contact details). Never a 422/500 that would stop the form — see the class
     * docblock.
     */
    public function verifyGst(Request $request, GstVerificationService $service): JsonResponse
    {
        $data = $request->validate([
            'gst_number' => ['required', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
        ]);

        if (! SurepassSettings::isConfigured()) {
            Log::warning('GST verification skipped — Surepass not configured', [
                'environment' => SurepassSettings::environment(),
            ]);

            return response()->json([
                'status' => 'unavailable',
                'message' => 'GST verification is not configured yet. You can continue — this can be verified later.',
            ]);
        }

        $result = $service->verifyAdvance($data['gst_number']);

        return response()->json($result);
    }
}
