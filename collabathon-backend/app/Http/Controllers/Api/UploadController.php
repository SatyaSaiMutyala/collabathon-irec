<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Upload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Generic "upload one file now, link it later" endpoint. Built for Complete Profile's
 * step 3 — a PAN/Aadhaar/RERA/GST scan is stored the moment it's picked instead of
 * riding along in the same multipart request as the rest of the step-3 form, which
 * was timing out on a slow connection carrying four files at once (the request never
 * got a chance to fail *individually* per file — it either finished as a whole or not
 * at all). One small request per file fails independently and can be retried on its
 * own without losing everything else already picked.
 *
 * Not tied to registration in any way beyond the `type` values below — any
 * authenticated screen that wants "pick a file, get a path back to send with a later
 * save" can reuse this rather than growing its own inline multipart branch the way
 * AuthController::saveRegistrationStep originally did for every attachment field.
 */
class UploadController extends Controller
{
    /** What a caller is allowed to say a file is for — keeps `type` from being freeform. */
    private const TYPES = [
        'photo', 'pan_card', 'aadhaar', 'rera_certificate', 'gst', 'cheque', 'signature',
    ];

    /**
     * POST /api/v1/uploads — stores the file and records who it belongs to and what
     * it's for. Returns `path`, not just a URL: it's the `path` a later save (e.g.
     * `saveRegistrationStep`'s `pan_card_path` etc.) sends back to be linked, and the
     * only thing that request is trusted on is that exact string matching a row here
     * owned by the same user.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'type' => ['required', 'string', Rule::in(self::TYPES)],
        ]);

        $disk = 'public';
        $folder = $data['type'] === 'photo' ? 'broker-photos' : 'broker-documents';
        $file = $request->file('file');
        $path = $file->store($folder, $disk);

        $upload = $request->user()->uploads()->create([
            'type' => $data['type'],
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);

        return response()->json([
            'data' => [
                'id' => $upload->id,
                'type' => $upload->type,
                'path' => $upload->path,
                'url' => Storage::disk($disk)->url($path),
            ],
        ], 201);
    }
}
