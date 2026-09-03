<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\Developer;
use App\Models\User;
use App\Services\DeveloperCredentialsNotifier;
use App\Services\MasterDataClient;
use App\Support\FileStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as ManualPaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Browses the irecexpo.com "Master Data" feed — developer/project registrations
 * submitted on that site — and converts one into a real Developer account here.
 *
 * The feed is entirely external: nothing under this controller reads or writes it, it
 * only ever calls out through {@see MasterDataClient} and reads the JSON that comes
 * back. `index()` caches each row it renders (keyed by registration_id) so `show()` and
 * `convert()` — reached by clicking through, not by re-querying — don't need a second
 * round trip for data already in hand; see {@see record()} for what happens when that
 * cache has expired.
 */
class MasterDataController extends Controller
{
    use HandlesListQueries;

    /** How long a listed row stays available to show()/convert() without a fresh fetch. */
    private const CACHE_MINUTES = 20;

    protected function defaultPerPage(): int
    {
        return 15;
    }

    protected function maxPerPage(): int
    {
        return 50;
    }

    public function index(Request $request, MasterDataClient $client): View
    {
        $this->authorize('view-module', 'master_data');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = $this->perPage($request);

        $result = $client->list([
            'page' => $page,
            'limit' => $perPage,
            'search' => $request->query('search'),
            'city' => $request->query('city'),
            'status' => $request->query('status'),
            'dev' => $request->query('dev'),
            'type' => $request->query('type'),
            'bhk' => $request->query('bhk'),
            'sort' => 'created_at',
            'order' => 'DESC',
        ]);

        $records = collect($result['records'])->map(function (array $record) {
            Cache::put($this->cacheKey((int) $record['registration_id']), $record, now()->addMinutes(self::CACHE_MINUTES));

            return $record;
        });

        // Which of this page's rows are already a real Developer — checked in one
        // query against every reference_code on the page, not one query per row.
        $convertedCodes = Developer::whereIn('external_reference_code', $records->pluck('reference_code')->filter())
            ->pluck('id', 'external_reference_code');

        return view('admin.master-data.index', [
            'apiOk' => $result['ok'],
            'apiError' => $result['error'],
            'records' => $this->paginator($records->all(), $result['pagination'], $page, $perPage, $request),
            'convertedCodes' => $convertedCodes,
        ]);
    }

    public function show(Request $request, int $registrationId, MasterDataClient $client): View|RedirectResponse
    {
        $this->authorize('view-module', 'master_data');

        $record = $this->record($registrationId, $client);

        if (! $record) {
            return redirect()->route('admin.master-data')
                ->with('warning', 'That registration could not be found — it may have expired from view. Try opening it again from the list.');
        }

        $developer = Developer::where('external_reference_code', $record['reference_code'] ?? null)
            ->with('user:id,email,status')
            ->first();

        return view('admin.master-data.show', [
            'record' => $record,
            'developer' => $developer,
        ]);
    }

    /**
     * Creates a real Developer + login account from one Master Data registration —
     * the one-way action that turns an external sign-up into an actual partner here.
     * Idempotent: converting the same registration twice redirects to the developer
     * created the first time rather than erroring or creating a duplicate.
     */
    public function convert(
        Request $request,
        int $registrationId,
        MasterDataClient $client,
        DeveloperCredentialsNotifier $notifier
    ): RedirectResponse {
        $this->authorize('edit-module', 'master_data');

        $record = $this->record($registrationId, $client);

        if (! $record) {
            return redirect()->route('admin.master-data')
                ->with('warning', 'That registration could not be found — it may have expired from view. Try opening it again from the list.');
        }

        $referenceCode = $record['reference_code'] ?? null;

        $existing = $referenceCode ? Developer::where('external_reference_code', $referenceCode)->first() : null;
        if ($existing) {
            return redirect()->route('admin.developers.show', $existing)
                ->with('info', "Already converted — {$existing->company_name} was created from this registration earlier.");
        }

        $data = $this->mapDeveloperFields($record);

        try {
            $this->guardUnique($data);
        } catch (ValidationException $e) {
            return redirect()->route('admin.master-data.show', $registrationId)
                ->with('error', $e->getMessage());
        }

        $logoPath = $this->downloadLogo($record['developer_profile']['builder_logo_url'] ?? null);

        $password = Str::password(14, symbols: false);

        $user = DB::transaction(function () use ($data, $password, $logoPath) {
            $user = User::create([
                'name' => $data['contact_person'],
                'email' => $data['email'],
                'password' => $password,
                'mobile' => $data['mobile'],
                'role' => User::ROLE_DEVELOPER,
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ]);

            Developer::create($data + [
                'user_id' => $user->id,
                'logo_path' => $logoPath,
                'verified' => false,
                'status' => 'active',
            ]);

            return $user;
        });

        $delivery = $notifier->send($user, $password, $data['contact_person']);

        $developer = $user->developer;

        return redirect()
            ->route('admin.developers.show', $developer)
            ->with('success', "{$data['company_name']} converted to a developer account.{$delivery['note']}")
            ->with('credentials', [
                'name' => $data['contact_person'],
                'email' => $data['email'],
                'password' => $password,
            ]);
    }

    /** Cache first (the normal path — reached by clicking through the list just rendered), API second. */
    private function record(int $registrationId, MasterDataClient $client): ?array
    {
        return Cache::remember(
            $this->cacheKey($registrationId),
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => $client->find($registrationId),
        );
    }

    private function cacheKey(int $registrationId): string
    {
        return "master-data:record:{$registrationId}";
    }

    /**
     * Every field App\Http\Controllers\Admin\DeveloperController::store() collects,
     * pulled from the registration's `developer_profile` block. `project_details` (the
     * project itself — units, amenities, commercials) is deliberately NOT imported here;
     * converting creates the developer's account, not a listing.
     */
    private function mapDeveloperFields(array $record): array
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
            'company_name' => (string) data_get($profile, 'company_name', ''),
            'contact_person' => (string) data_get($profile, 'key_contact_person', ''),
            'contact_designation' => data_get($profile, 'designation') ?: null,
            'email' => (string) data_get($profile, 'email', ''),
            'mobile' => $this->normaliseMobile((string) data_get($profile, 'mobile', '')),
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

    /**
     * Checked before creating, not left to the database, so a collision surfaces as a
     * clear message on the Master Data page rather than a raw constraint-violation 500.
     */
    private function guardUnique(array $data): void
    {
        if ($data['email'] === '' || $data['mobile'] === '' || $data['company_name'] === '') {
            throw ValidationException::withMessages([
                'error' => ['This registration is missing a required field (email, mobile or company name) and cannot be converted yet.'],
            ]);
        }

        if (User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'error' => ["{$data['email']} is already in use by another account — resolve that before converting."],
            ]);
        }

        if (User::where('mobile', $data['mobile'])->exists()) {
            throw ValidationException::withMessages([
                'error' => ["{$data['mobile']} is already in use by another account — resolve that before converting."],
            ]);
        }

        if (Developer::where('company_name', $data['company_name'])->exists()) {
            throw ValidationException::withMessages([
                'error' => ["A developer named \"{$data['company_name']}\" already exists — resolve that before converting."],
            ]);
        }
    }

    /**
     * Best-effort: a broken or slow image fetch must not block the whole conversion,
     * since the account and its access are the part that actually matters.
     */
    private function downloadLogo(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        try {
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'png';
            $folder = 'developers/logos';
            $path = $folder . '/' . Str::random(24) . '.' . $extension;

            FileStorage::diskForFolder($folder)->put($path, $response->body());

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Master Data logo download failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function normaliseMobile(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    /**
     * A real LengthAwarePaginator built from the vendor's own pagination block, so
     * `<x-pagination>`/`<x-data-table>` render exactly as they do for an Eloquent
     * paginator — no special-casing anywhere else for "this page came from an API".
     *
     * @param  array<int,array>  $items
     */
    private function paginator(array $items, array $apiPagination, int $page, int $perPage, Request $request): LengthAwarePaginator
    {
        return new ManualPaginator(
            $items,
            (int) ($apiPagination['total_records'] ?? count($items)),
            (int) ($apiPagination['per_page'] ?? $perPage),
            (int) ($apiPagination['current_page'] ?? $page),
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }
}
