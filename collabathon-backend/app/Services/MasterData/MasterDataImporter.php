<?php

namespace App\Services\MasterData;

use App\Exceptions\MasterDataImportException;
use App\Models\Developer;
use App\Models\Property;
use App\Models\PropertyDetail;
use App\Models\PropertyMedia;
use App\Models\PropertyUnitType;
use App\Models\User;
use App\Services\DeveloperCredentialsNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns one irecexpo.com registration into a developer account and a listing here.
 *
 * The shape of the vendor's data drives the whole design: a registration is one
 * *project*, filed by a developer who files a fresh one for every project they launch.
 * So the second registration from a company we already know must not try to create
 * that company again — it brings only the listing, hung off the account the first
 * registration created. That is why developer resolution below looks past the
 * reference code (which identifies a registration, not a company) to the identity
 * fields, and why the only collisions left to reject are the ones that would hand a
 * broker's or an admin's login to a developer.
 *
 * Ordering is deliberate throughout:
 *
 *  1. resolve and validate, writing nothing, so a rejected import leaves no trace;
 *  2. create the account and the listing rows in one transaction;
 *  3. pull the remote images and documents afterwards.
 *
 * Step 3 sits outside the transaction because it is a dozen HTTP calls to someone
 * else's CDN, and holding a write transaction open across those is how a slow image
 * host turns into a locked table. It also has to run last for a plainer reason: assets
 * are stored under `properties/{id}`, and that id does not exist until the insert.
 * A download that fails costs the listing a photo, which the admin can re-upload — see
 * {@see RemoteAsset}.
 */
class MasterDataImporter
{
    /**
     * How long the request is allowed to run once it reaches the asset stage, and how
     * much of that the assets themselves may spend. The gap between the two is
     * deliberate: the deadline should be what stops the loop, leaving room to finish the
     * file in hand and return a page, rather than the request being killed mid-upload.
     */
    private const ASSET_TIME_LIMIT_SECONDS = 300;

    private const ASSET_BUDGET_SECONDS = 240;

    public function __construct(
        private readonly DeveloperMapper $developers,
        private readonly ProjectMapper $projects,
        private readonly RemoteAsset $assets,
        private readonly DeveloperCredentialsNotifier $credentials,
    ) {}

    /**
     * @param  array<string, mixed>  $record  One record from the Master Data feed.
     *
     * @throws MasterDataImportException when the registration cannot be imported as it stands.
     */
    public function import(array $record): ImportOutcome
    {
        $reference = $record['reference_code'] ?? null;

        // Idempotence. Converting the same registration twice is a double-click or a
        // back-button away, and neither should produce a second copy of the listing.
        if ($imported = $this->alreadyImported($reference)) {
            return $imported;
        }

        $profile = $this->developers->map($record);
        $developer = $this->findDeveloper($profile);

        // Mapped before any write so a project the mappers reject — an unknown project
        // type, a registration with no project name — fails without having created a
        // developer account that would then be left behind with nothing attached.
        $propertyAttributes = $this->projects->propertyAttributes($record);
        $detailAttributes = $this->projects->detailAttributes($record);
        $unitTypes = $this->projects->unitTypes($record);

        $password = null;
        $logoPath = null;

        if (! $developer) {
            $this->guardIdentity($profile);
            // The developer's logo lives on the profile, not the project, and its folder
            // needs no id — so unlike the listing's assets it can be fetched up front.
            $logoPath = $this->assets->fetch($this->developers->logoUrl($record), 'developers/logos');
            $password = Str::password(14, symbols: false);
        }

        [$developer, $property] = DB::transaction(function () use ($developer, $profile, $password, $logoPath, $propertyAttributes, $detailAttributes, $unitTypes) {
            if (! $developer) {
                $developer = $this->createDeveloper($profile, $password, $logoPath);
            }

            $property = Property::create($propertyAttributes + [
                'developer_id' => $developer->id,
                // Imported listings go live the way an admin-created one does. "Live"
                // still means the developer has to accept the assignment before any
                // broker sees it — Property::isVisibleToBrokers() needs both keys — so
                // this publishes it without bypassing that consent.
                'listing_status' => 'active',
            ]);

            PropertyDetail::create($detailAttributes + ['property_id' => $property->id]);

            foreach ($unitTypes as $row) {
                PropertyUnitType::create($row + ['property_id' => $property->id]);
            }

            return [$developer, $property];
        });

        $this->attachAssets($property, $record);

        $delivery = $password
            ? $this->credentials->send($developer->user, $password, $profile['contact_person'])
            : null;

        return new ImportOutcome(
            developer: $developer,
            property: $property->refresh(),
            developerCreated: $password !== null,
            propertyCreated: true,
            credentials: $password ? [
                'name' => $profile['contact_person'],
                'email' => $profile['email'],
                'password' => $password,
            ] : null,
            deliveryNote: $delivery['note'] ?? '',
            catalogueAdditions: $this->projects->catalogueAdditions(),
        );
    }

    /**
     * Which developer this registration would attach to, without importing anything —
     * what the detail page needs to say "add this listing to <company>" before the
     * admin commits to it.
     *
     * The half-built-account case is swallowed here rather than raised: this is a
     * read-only lookup on a page that has to render, and pressing the button surfaces
     * the same problem with the explanation attached.
     */
    public function existingDeveloper(array $record): ?Developer
    {
        try {
            return $this->findDeveloper($this->developers->map($record));
        } catch (MasterDataImportException) {
            return null;
        }
    }

    // ------------------------------------------------------------------ resolution

    /**
     * The listing this registration already produced, if it produced one.
     *
     * Trashed listings count. A registration whose listing was deleted has still been
     * imported, and re-importing it would resurrect the same project as a second row
     * that the first one's unique reference code would then collide with anyway —
     * better to send the admin to the copy in Trash and let them restore it.
     */
    private function alreadyImported(?string $reference): ?ImportOutcome
    {
        if (! $reference) {
            return null;
        }

        $property = Property::withTrashed()
            ->where('external_reference_code', $reference)
            ->first();

        if (! $property) {
            return null;
        }

        // withTrashed on the developer too: a soft-deleted developer still owns their
        // listings, and the pair belongs in Trash together rather than half-resurrected
        // by an import that thought the company was gone.
        $developer = $property->developer()->withTrashed()->first()
            ?? throw MasterDataImportException::orphanedListing($reference);

        return ImportOutcome::alreadyImported($developer, $property);
    }

    /**
     * The developer this registration belongs to, if they are already on the platform.
     *
     * Four ways in, most specific first. The reference code only ever matches when this
     * exact registration created the developer, so on a repeat registration it is the
     * identity fields that do the work — which is the entire fix for "one developer, one
     * listing". Company name comes last and case-insensitively, as the loosest of the
     * three and the one most likely to differ in punctuation between filings.
     */
    private function findDeveloper(array $profile): ?Developer
    {
        if ($reference = $profile['external_reference_code'] ?? null) {
            if ($developer = Developer::where('external_reference_code', $reference)->first()) {
                return $developer;
            }
        }

        if ($profile['email'] !== '') {
            if ($user = User::where('email', $profile['email'])->where('role', User::ROLE_DEVELOPER)->first()) {
                if ($developer = $user->developer) {
                    return $developer;
                }

                // The login outlived its company row, which means one of two things and
                // they need different answers: the company is in Trash and should be
                // restored, or it is genuinely gone and the account is half-built.
                // Quietly creating the missing half here would paper over either.
                if ($trashed = $user->developer()->onlyTrashed()->first()) {
                    throw MasterDataImportException::developerInTrash($trashed->company_name);
                }

                throw MasterDataImportException::developerAccountIncomplete($profile['email']);
            }
        }

        if ($profile['mobile'] !== '') {
            if ($developer = Developer::where('mobile', $profile['mobile'])->first()) {
                return $developer;
            }
        }

        if ($profile['company_name'] !== '') {
            return Developer::whereRaw('LOWER(company_name) = ?', [Str::lower($profile['company_name'])])->first();
        }

        return null;
    }

    /**
     * Checked before creating rather than left to the database, so a collision surfaces
     * as a sentence the admin can act on instead of a constraint-violation 500.
     *
     * Only reached when no existing developer matched, so anything found here belongs to
     * a broker or an admin — a genuine conflict, since one email is one login. A
     * developer's own email never lands here; it resolves in findDeveloper() and the
     * import reuses the account.
     *
     * @throws MasterDataImportException
     */
    private function guardIdentity(array $profile): void
    {
        foreach (['company_name' => 'company name', 'contact_person' => 'contact person', 'email' => 'email address', 'mobile' => 'mobile number'] as $key => $label) {
            if (($profile[$key] ?? '') === '') {
                throw MasterDataImportException::missingField($label);
            }
        }

        // A developer whose company sits in Trash never matches findDeveloper(), whose
        // query excludes the soft-deleted — so without this the admin would be told
        // their own developer's email "belongs to a developer account", which is true
        // and useless. Restoring is the answer, and this is where to say so.
        if ($trashed = Developer::onlyTrashed()->where('email', $profile['email'])->first()) {
            throw MasterDataImportException::developerInTrash($trashed->company_name);
        }

        if ($owner = User::where('email', $profile['email'])->first()) {
            throw MasterDataImportException::emailBelongsToAnotherRole($profile['email'], $owner->role);
        }

        if ($owner = User::where('mobile', $profile['mobile'])->first()) {
            throw MasterDataImportException::mobileBelongsToAnotherRole($profile['mobile'], $owner->role);
        }
    }

    // ------------------------------------------------------------------ writes

    private function createDeveloper(array $profile, string $password, ?string $logoPath): Developer
    {
        $user = User::create([
            'name' => $profile['contact_person'],
            'email' => $profile['email'],
            'password' => $password,
            'mobile' => $profile['mobile'],
            'role' => User::ROLE_DEVELOPER,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        return Developer::create($profile + [
            'user_id' => $user->id,
            'logo_path' => $logoPath,
            'verified' => false,
            'status' => 'active',
        ]);
    }

    /**
     * Downloads the registration's images and documents and files them against the
     * listing. Runs after the transaction — see the class docblock — so each failure
     * costs one asset rather than the import.
     *
     * This is the slow part by a wide margin, and it has to be bounded twice over. One
     * registration carries ~97 MB of full-resolution photos, each of which is fetched
     * from the vendor, re-encoded, and pushed to S3 — comfortably past PHP's default
     * 30-second ceiling, which is what turned a working import into a fatal error rather
     * than a listing. So the limit is lifted for the stretch that needs it, and a
     * deadline stops the run regardless if the vendor's host has gone slow. Passing the
     * deadline is not a failure: the listing and every asset already filed are kept, and
     * the rest are logged by name.
     */
    private function attachAssets(Property $property, array $record): void
    {
        $folder = "properties/{$property->id}";

        // Only for this stretch, and only upward. A cheap listing still finishes in
        // seconds; this is what keeps a heavy one from dying two thirds of the way in.
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::ASSET_TIME_LIMIT_SECONDS);
        }

        $deadline = microtime(true) + self::ASSET_BUDGET_SECONDS;

        foreach ($this->projects->assets($record) as $index => $spec) {
            if (microtime(true) > $deadline) {
                Log::warning('Master Data asset import ran out of time — the rest were skipped', [
                    'property_id' => $property->id,
                    'imported' => $index,
                    'remaining' => count($this->projects->assets($record)) - $index,
                ]);

                return;
            }

            $path = $this->assets->fetch($spec['url'], $folder);

            if ($path === null) {
                continue;
            }

            // The hero image is the listing's cover on our side, a column rather than a
            // media row — and only the first one to arrive, so a second hero-shaped
            // asset can never quietly replace it.
            if ($spec['kind'] === 'cover') {
                if (! $property->cover_image_path) {
                    $property->update(['cover_image_path' => $path]);
                }

                continue;
            }

            PropertyMedia::create([
                'property_id' => $property->id,
                'kind' => $spec['kind'],
                'path' => $path,
                'caption' => $spec['caption'],
                'sort_order' => $spec['sort_order'],
            ]);
        }
    }
}
