<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MasterDataImportException;
use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\MasterData\ImportOutcome;
use App\Services\MasterData\MasterDataImporter;
use App\Services\MasterDataClient;
use App\Services\ProjectAssignmentNotifier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as ManualPaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Browses the irecexpo.com "Master Data" feed — developer/project registrations
 * submitted on that site — and imports one as a real developer account and listing.
 *
 * The feed is entirely external: nothing under this controller reads or writes it, it
 * only ever calls out through {@see MasterDataClient} and reads the JSON that comes
 * back. `index()` caches each row it renders (keyed by registration_id) so `show()` and
 * `convert()` — reached by clicking through, not by re-querying — don't need a second
 * round trip for data already in hand; see {@see record()} for what happens when that
 * cache has expired.
 *
 * The import itself lives in {@see MasterDataImporter}. What stays here is a
 * controller's share of it: fetch the record, catch the one exception the importer
 * raises, and turn what it did into the right message.
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

        // Which of this page's rows are already in — checked against listings rather
        // than developers, because a registration is one project and a listing is what
        // it becomes. A developer with three registrations shows three rows here, two
        // of which may be imported and one not, which the developer table could not
        // express. One query for the whole page, not one per row.
        $convertedCodes = Property::withTrashed()
            ->whereIn('external_reference_code', $records->pluck('reference_code')->filter())
            ->pluck('id', 'external_reference_code');

        return view('admin.master-data.index', [
            'apiOk' => $result['ok'],
            'apiError' => $result['error'],
            'records' => $this->paginator($records->all(), $result['pagination'], $page, $perPage, $request),
            'convertedCodes' => $convertedCodes,
        ]);
    }

    public function show(
        Request $request,
        int $registrationId,
        MasterDataClient $client,
        MasterDataImporter $importer
    ): View|RedirectResponse {
        $this->authorize('view-module', 'master_data');

        $record = $this->record($registrationId, $client);

        if (! $record) {
            return redirect()->route('admin.master-data')
                ->with('warning', 'That registration could not be found — it may have expired from view. Try opening it again from the list.');
        }

        // Three states the page has to tell apart, each with its own button: nothing
        // here yet, the company is here but this project is not, or both are. The
        // developer is resolved by the importer itself, so the page never offers
        // something the import would then do differently.
        $property = Property::withTrashed()
            ->with('developer')
            ->where('external_reference_code', $record['reference_code'] ?? null)
            ->first();

        return view('admin.master-data.show', [
            'record' => $record,
            'property' => $property,
            'developer' => $property?->developer ?? $importer->existingDeveloper($record),
        ]);
    }

    /**
     * Imports one Master Data registration: the developer's account when they are new
     * here, and the project itself as a listing either way.
     *
     * A registration is one *project*, and a developer files a fresh one for every
     * project they launch — so the second registration from a company already on the
     * platform brings only its listing, hung off the account the first one created.
     * That resolution and the rules around it live in {@see MasterDataImporter}; this
     * method only decides what to say about the result.
     *
     * Idempotent: converting the same registration twice opens the listing it produced
     * the first time rather than erroring or creating a duplicate.
     */
    public function convert(
        Request $request,
        int $registrationId,
        MasterDataClient $client,
        MasterDataImporter $importer,
        ProjectAssignmentNotifier $notifier
    ): RedirectResponse {
        $this->authorize('edit-module', 'master_data');

        $record = $this->record($registrationId, $client);

        if (! $record) {
            return redirect()->route('admin.master-data')
                ->with('warning', 'That registration could not be found — it may have expired from view. Try opening it again from the list.');
        }

        try {
            $outcome = $importer->import($record);
        } catch (MasterDataImportException $e) {
            // The only exception the importer raises, and every message it carries is
            // already written for this page — so it is shown as it is rather than
            // restated as something vaguer here.
            return redirect()->route('admin.master-data.show', $registrationId)
                ->with('error', $e->getMessage());
        }

        if (! $outcome->propertyCreated) {
            return redirect()->route('admin.properties.show', $outcome->property)
                ->with('info', "Already imported — \"{$outcome->property->name}\" was created from this registration earlier.");
        }

        // The same notification an admin-created listing sends, through the same
        // service: the developer needs the accept/decline links before a broker can see
        // this listing at all.
        $notifier->assigned($outcome->property);

        return redirect()
            ->route('admin.properties.show', $outcome->property)
            ->with('success', $this->importMessage($outcome))
            // Its own notice rather than more text on the success message: this is not
            // what the admin asked for, it is a side effect they may want to undo, and
            // it should read as one.
            ->with('warning', $this->catalogueNote($outcome))
            ->with('credentials', $outcome->credentials);
    }

    /**
     * What the import added to the editable lists, if anything.
     *
     * The vendor's fields are free text, so a registration can carry a project type or a
     * state nobody here has ever used — sometimes a genuine new value, sometimes a
     * developer typing into the wrong box on their form. Both are added so the import
     * never dead-ends, and both are named here so the admin can go and tidy up the
     * second kind. Added entries are inactive, so nothing new is offered on a form or a
     * filter until someone turns it on.
     */
    private function catalogueNote(ImportOutcome $outcome): ?string
    {
        if ($outcome->catalogueAdditions === []) {
            return null;
        }

        return implode('. ', $outcome->catalogueAdditions)
            . '. Added switched off, so nothing new appears on a form until you enable it in Settings.';
    }

    /**
     * What the import did, in one sentence.
     *
     * The two cases read differently on purpose. A new account is news the admin has to
     * act on, because credentials have just gone out to someone; another listing for a
     * developer already on the platform is routine, and naming the company is what
     * confirms the listing landed on the right one.
     */
    private function importMessage(ImportOutcome $outcome): string
    {
        $listing = "\"{$outcome->property->name}\" is live under {$outcome->developer->company_name}, "
            .'pending their acceptance.';

        return $outcome->developerCreated
            ? "{$outcome->developer->company_name} converted to a developer account. {$listing}{$outcome->deliveryNote}"
            : $listing;
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
