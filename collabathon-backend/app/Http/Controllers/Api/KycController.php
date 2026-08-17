<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AadhaarVerificationService;
use App\Services\PanVerificationService;
use App\Support\SurepassSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Document verification for the Complete Profile screen — Aadhaar today, PAN and GST
 * once their endpoints are wired in the same way. Public, not `auth:sanctum`: this
 * runs mid-registration, before a channel partner has an account or a token yet.
 *
 * Every method here answers with a 200 and a `status` field even when verification
 * itself failed — a bad photo or an unreachable Surepass must not surface as a 422/500
 * that blocks the form. Registration always proceeds either way; verification is a
 * confidence signal on top of it, not a gate in front of it (see docs/KYC_VERIFICATION_PROVIDER_SETUP.md
 * for why: Surepass has no uptime guarantee this app controls, so a hard gate here
 * would let a third party's outage stop channel partners from signing up at all).
 */
class KycController extends Controller
{
    /**
     * POST /api/v1/kyc/aadhaar/verify — photo of the physical Aadhaar card in, the
     * card's own QR code decoded and checked against Surepass out.
     */
    public function verifyAadhaar(Request $request, AadhaarVerificationService $service): JsonResponse
    {
        $request->validate([
            'aadhaar_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        if (! SurepassSettings::isConfigured()) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'Aadhaar verification is not configured yet. You can continue — this can be verified later.',
            ]);
        }

        $result = $service->verifyFromImage($request->file('aadhaar_photo')->getRealPath());

        return response()->json($result);
    }

    /**
     * POST /api/v1/kyc/aadhaar/verify-xml — the offline XML a broker downloads
     * themselves from the UIDAI website in, verified data out. `share_code` is the
     * 4-digit code UIDAI has the downloader set at download time; optional here
     * because Surepass, not this endpoint, is the one that knows whether a given
     * file actually needs it.
     */
    public function verifyAadhaarXml(Request $request, AadhaarVerificationService $service): JsonResponse
    {
        $data = $request->validate([
            'aadhaar_xml' => ['required', 'file', 'mimes:xml', 'max:2048'],
            'share_code' => ['nullable', 'string', 'max:10'],
        ]);

        if (! SurepassSettings::isConfigured()) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'Aadhaar verification is not configured yet. You can continue — this can be verified later.',
            ]);
        }

        $result = $service->verifyFromXml(
            $request->file('aadhaar_xml')->getRealPath(),
            $data['share_code'] ?? null,
        );

        return response()->json($result);
    }

    /**
     * POST /api/v1/kyc/aadhaar/verify-eaadhaar — the eAadhaar PDF a broker downloads
     * themselves from the UIDAI website in, verified data out. `yob`/`full_name` are
     * what a password-protected copy needs to open — optional here for the same
     * reason `share_code` is optional on the XML endpoint: Surepass, not this one,
     * knows whether a given file actually needs them.
     */
    public function verifyAadhaarEaadhaar(Request $request, AadhaarVerificationService $service): JsonResponse
    {
        $data = $request->validate([
            'aadhaar_eaadhaar' => ['required', 'file', 'mimes:pdf', 'max:8192'],
            'yob' => ['nullable', 'digits:4'],
            'full_name' => ['nullable', 'string', 'max:255'],
        ]);

        if (! SurepassSettings::isConfigured()) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'Aadhaar verification is not configured yet. You can continue — this can be verified later.',
            ]);
        }

        $result = $service->verifyFromEaadhaar(
            $request->file('aadhaar_eaadhaar')->getRealPath(),
            $data['yob'] ?? null,
            $data['full_name'] ?? null,
        );

        return response()->json($result);
    }

    /**
     * POST /api/v1/kyc/pan/verify — a PAN number in, Surepass's PAN Comprehensive
     * data out (name, DOB, gender, address, masked Aadhaar). Same non-blocking
     * contract as the Aadhaar endpoints above: always a 200 with a `status`
     * field, never a 422/500 that would stop the form.
     */
    public function verifyPan(Request $request, PanVerificationService $service): JsonResponse
    {
        $data = $request->validate([
            'pan_number' => ['required', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
        ]);

        if (! SurepassSettings::isConfigured()) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'PAN verification is not configured yet. You can continue — this can be verified later.',
            ]);
        }

        $result = $service->verifyComprehensive($data['pan_number']);

        return response()->json($result);
    }
}
