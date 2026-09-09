<?php

namespace App\Services\MasterData;

/**
 * One Master Data registration's `developer_profile` block, in the shape
 * {@see \App\Models\Developer} and its login account expect.
 *
 * Lifted out of MasterDataController so the importer can map a record without the
 * controller in the picture, and so this mapping has one home when the vendor adds a
 * field. Every value here comes from the registration; nothing is invented except the
 * documented commission fallback at the end.
 */
class DeveloperMapper
{
    /**
     * Every field App\Http\Controllers\Admin\DeveloperController::store() collects,
     * pulled from the registration's `developer_profile` block. The project itself is
     * mapped separately by {@see ProjectMapper} — this half creates the account, that
     * half creates the listing.
     *
     * @return array<string, mixed>
     */
    public function map(array $record): array
    {
        $profile = $record['developer_profile'] ?? [];
        $social = $profile['social_links'] ?? [];
        $commission = $record['project_details']['channel_partner_commercials']['cp_commission'] ?? null;

        // data_get(), not $profile['key'] ?: null — every one of these is an optional
        // field on the vendor's side (confirmed: their own sample payload already
        // ships some social links as absent), and `?:` throws on a genuinely missing
        // array key rather than just falling through on an empty one.
        return [
            'external_reference_code' => $record['reference_code'] ?? null,
            'company_name' => trim((string) data_get($profile, 'company_name', '')),
            'contact_person' => trim((string) data_get($profile, 'key_contact_person', '')),
            'contact_designation' => data_get($profile, 'designation') ?: null,
            'email' => trim((string) data_get($profile, 'email', '')),
            'mobile' => $this->normaliseMobile((string) data_get($profile, 'mobile', '')),
            // The vendor sends exactly one contact — the same person fills both our
            // public "Contact person" and the admin-only "Key contact" panel, since
            // there's nothing in the payload to tell the two apart. An admin can
            // still edit Key contact separately later if a different internal
            // decision-maker turns out to be the right one there.
            'key_contact_person' => data_get($profile, 'key_contact_person') ?: null,
            'key_contact_designation' => data_get($profile, 'designation') ?: null,
            'key_contact_mobile' => ($m = $this->normaliseMobile((string) data_get($profile, 'mobile', ''))) !== '' ? $m : null,
            'key_contact_email' => data_get($profile, 'email') ?: null,
            'about' => data_get($profile, 'about_company') ?: (data_get($profile, 'brief_description') ?: null),
            'website' => data_get($profile, 'website') ?: null,
            'address' => data_get($profile, 'registered_address') ?: null,
            'city' => data_get($profile, 'city') ?: null,
            'state' => data_get($profile, 'state') ?: null,
            'country' => data_get($profile, 'country') ?: null,
            'pincode' => data_get($profile, 'pincode') ?: null,
            'instagram' => data_get($social, 'instagram') ?: null,
            'facebook' => data_get($social, 'facebook') ?: null,
            'youtube' => data_get($social, 'youtube') ?: null,
            'twitter' => data_get($social, 'twitter_x') ?: null,
            'linkedin' => data_get($social, 'linkedin') ?: null,
            // 2.50 matches DeveloperController::store()'s own default — this is
            // commission a channel partner earns from us, not necessarily the same
            // figure the developer quoted the vendor's site for their own listing, so
            // it's only trusted when it actually looks like a plain percentage.
            'cp_payout_percent' => is_numeric($commission) ? (float) $commission : 2.50,
        ];
    }

    /** The developer's logo, which lives on the profile rather than on any one project. */
    public function logoUrl(array $record): ?string
    {
        return data_get($record, 'developer_profile.builder_logo_url') ?: null;
    }

    public function normaliseMobile(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}
