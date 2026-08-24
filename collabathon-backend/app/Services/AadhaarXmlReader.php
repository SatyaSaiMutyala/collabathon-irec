<?php

namespace App\Services;

/**
 * Parses the UIDAI-signed Aadhaar XML DigilockerController re-hosts after a
 * verification — the same read used by both the admin panel's formatted
 * preview (ApprovalController::aadhaarPreview) and the broker's own
 * (AuthController::aadhaarPreview), so the two never drift.
 */
class AadhaarXmlReader
{
    /**
     * @return array{maskedAadhaar: string, name: string, dob: string, gender: string, address: string, photoBase64: string}|null
     *         null when this isn't parseable as a genuine signed Aadhaar XML.
     */
    public static function read(string $xmlContent): ?array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);

        if ($xml === false) {
            return null;
        }

        $uidData = $xml->CertificateData->KycRes->UidData;
        $maskedAadhaar = (string) ($uidData['uid'] ?? '');

        // The one field that's always present in a genuine signed Aadhaar XML —
        // its absence means this wasn't parseable as one, whatever the file
        // actually was.
        if ($maskedAadhaar === '') {
            return null;
        }

        $poi = $uidData->Poi;
        $poa = $uidData->Poa;

        return [
            'maskedAadhaar' => $maskedAadhaar,
            'name' => (string) ($poi['name'] ?? ''),
            'dob' => (string) ($poi['dob'] ?? ''),
            'gender' => (string) ($poi['gender'] ?? ''),
            'address' => collect([
                (string) ($poa['co'] ?? ''),
                (string) ($poa['loc'] ?? ''),
                (string) ($poa['vtc'] ?? ''),
                (string) ($poa['dist'] ?? ''),
                (string) ($poa['state'] ?? ''),
                (string) ($poa['country'] ?? ''),
                (string) ($poa['pc'] ?? ''),
            ])->filter()->implode(', '),
            'photoBase64' => (string) $uidData->Pht,
        ];
    }
}
