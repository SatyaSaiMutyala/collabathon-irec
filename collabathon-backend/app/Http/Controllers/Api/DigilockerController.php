<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Upload;
use App\Services\DigilockerVerificationService;
use App\Support\SurepassSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Aadhaar verification via DigiLocker — see DigilockerVerificationService's own
 * docblock for why this replaced the earlier QR/XML/eAadhaar-upload attempts.
 * `initialize`/`status` are public, same reasoning as KycController's PAN/GST
 * endpoints (this runs mid-registration, before the broker necessarily has a
 * token yet on the mobile-number path). `downloadAadhaar` is authenticated —
 * see its own docblock for why.
 */
class DigilockerController extends Controller
{
    private function unavailable(string $context): JsonResponse
    {
        Log::warning("DigiLocker {$context} skipped — Surepass not configured", [
            'environment' => SurepassSettings::environment(),
        ]);

        return response()->json([
            'status' => 'unavailable',
            'message' => 'Aadhaar verification is not configured yet. You can continue — this can be verified later.',
        ]);
    }

    /** POST /api/v1/kyc/digilocker/initialize — opens a session, returns the URL to open in a WebView. */
    public function initialize(Request $request, DigilockerVerificationService $service): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        if (! SurepassSettings::isConfigured()) {
            return $this->unavailable('initialize');
        }

        return response()->json(
            $service->initialize($data['full_name'] ?? null, $data['mobile'] ?? null, $data['email'] ?? null),
        );
    }

    /** GET /api/v1/kyc/digilocker/status/{clientId} — whether the broker actually finished the DigiLocker steps. */
    public function status(string $clientId, DigilockerVerificationService $service): JsonResponse
    {
        if (! SurepassSettings::isConfigured()) {
            return $this->unavailable('status');
        }

        return response()->json($service->status($clientId));
    }

    /**
     * GET /api/v1/kyc/digilocker/download-aadhaar/{clientId} — the verified
     * Aadhaar data, once status() says completed.
     *
     * Also re-hosts the UIDAI-signed XML DigiLocker handed back as a real Upload
     * row owned by the caller — the same "pick a file, get a path back to link
     * on the real submit" contract every other step-3 attachment already goes
     * through (see UploadController), so the mobile app can treat this exactly
     * like a manually-attached document without any special-casing. Surepass's
     * own `xml_url` is a temporary pre-signed link; it is never handed to the
     * client directly, because it isn't durable enough to still work by the time
     * the broker actually submits.
     */
    public function downloadAadhaar(Request $request, string $clientId, DigilockerVerificationService $service): JsonResponse
    {
        if (! SurepassSettings::isConfigured()) {
            return $this->unavailable('download-aadhaar');
        }

        $result = $service->downloadAadhaar($clientId);

        if ($result['status'] === 'verified' && filled($result['data']['xml_url'] ?? null)) {
            $document = $this->storeAadhaarXml($request, $result['data']['xml_url']);
            unset($result['data']['xml_url']);
            if ($document) {
                $result['data']['document'] = $document;
            }
        } else {
            unset($result['data']['xml_url']);
        }

        return response()->json($result);
    }

    /**
     * Best-effort, on purpose — a failure here (the pre-signed URL already
     * expired, a network blip) must not turn an otherwise-successful Aadhaar
     * verification into a failure. The broker just falls back to attaching a
     * copy by hand, same as before this existed.
     */
    private function storeAadhaarXml(Request $request, string $xmlUrl): ?array
    {
        try {
            $response = Http::timeout(20)->get($xmlUrl);
        } catch (Throwable $e) {
            Log::warning('DigiLocker Aadhaar XML fetch failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('DigiLocker Aadhaar XML fetch failed', ['status' => $response->status()]);

            return null;
        }

        $filename = 'aadhaar-digilocker-'.bin2hex(random_bytes(8)).'.xml';
        $path = 'broker-documents/'.$filename;
        // broker-documents/ is a secure prefix — FileStorage routes it to the
        // private disk, so the Aadhaar XML is never a publicly readable object.
        $disk = \App\Support\FileStorage::diskName('broker-documents');
        Storage::disk($disk)->put($path, $response->body());

        $upload = $request->user()->uploads()->create([
            'type' => 'aadhaar',
            'disk' => $disk,
            'path' => $path,
            'original_name' => $filename,
            'size' => strlen($response->body()),
        ]);

        return [
            'id' => $upload->id,
            'path' => $upload->path,
            'url' => \App\Support\FileStorage::url($path),
            'name' => $filename,
        ];
    }
}
