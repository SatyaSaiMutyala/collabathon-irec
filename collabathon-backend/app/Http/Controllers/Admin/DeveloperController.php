<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\ExportsList;
use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\Developer;
use App\Models\User;
use App\Support\CsvReader;
use App\Support\SocialPlatforms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeveloperController extends Controller
{
    use HandlesListQueries, ExportsList;

    protected function defaultPerPage(): int
    {
        return 15;
    }

    private const SORTABLE = [
        'created_at' => 'created_at',
        'name' => 'company_name',
        'payout' => 'cp_payout_percent',
        'listings' => 'properties_count',
    ];

    /** How many of a developer's projects the profile lists before linking out. */
    private const PROJECTS_ON_PROFILE = 8;

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\Response
    {
        $query = Developer::query()
            // The row menu shares the login email — eager-loaded to keep the list at
            // two queries rather than one per developer.
            ->with('user:id,email')
            ->withCount('properties')
            ->when($request->query('search'), function ($q, $term) {
                // Prefix match so the company_name index is usable.
                $q->where(fn ($w) => $w->where('company_name', 'like', $term . '%')
                    ->orWhere('contact_person', 'like', $term . '%')
                    ->orWhere('email', 'like', $term . '%'));
            })
            ->when($request->query('country'), fn ($q, $v) => $q->where('country', $v))
            ->when($request->query('city'), fn ($q, $v) => $q->where('city', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v));

        $query = $this->applySort($query, $request, self::SORTABLE);

        $grouped = $this->exportColumns();

        if ($format = $this->exportFormat($request)) {
            return $this->exportList($format, $query, 'developers', 'Developers',
                $this->flattenGroupedColumns($grouped), $request);
        }

        return view('admin.developers', [
            'developers' => $this->paginate($query, $request),
            // Only values that actually occur, so the filter can never return nothing.
            'cities' => Developer::query()->distinct()->orderBy('city')->pluck('city')->filter()->values(),
            'countries' => Developer::query()->distinct()->orderBy('country')->pluck('country')->filter()->values(),
            /*
             * Coverage, not commercials. Listing count and average payout belong to the
             * project and finance views — on a directory of companies the question an
             * admin is actually asking is "where do we have developers, and where don't
             * we". distinct() ignores NULL, so a developer with no country set is simply
             * not counted rather than showing up as an empty region.
             */
            'totals' => [
                'all' => Developer::count(),
                'active' => Developer::where('status', 'active')->count(),
                'countries' => Developer::distinct()->whereNotNull('country')->count('country'),
                'states' => Developer::distinct()->whereNotNull('state')->count('state'),
                'cities' => Developer::distinct()->whereNotNull('city')->count('city'),
            ],
            'export' => [
                'groups' => $this->exportGroupLabels($grouped),
                'templates' => $this->exportTemplates(),
            ],
        ]);
    }

    /**
     * Exportable columns, grouped for the picker: group label => key => [label, getter].
     * Flattened for the export by {@see \App\Http\Concerns\ExportsList}, in this order.
     *
     * @return array<string,array<string,array{0:string,1:callable}>>
     */
    private function exportColumns(): array
    {
        return [
            'Company' => [
                'company' => ['Company', fn (Developer $d) => $d->company_name],
                'website' => ['Website', fn (Developer $d) => $d->website],
                'rera_number' => ['RERA number', fn (Developer $d) => $d->rera_number],
                'verified' => ['Verified', fn (Developer $d) => $d->verified === null ? null : ($d->verified ? 'Yes' : 'No')],
                'status' => ['Status', fn (Developer $d) => ucfirst((string) $d->status)],
            ],
            'Contact' => [
                'contact_person' => ['Contact person', fn (Developer $d) => $d->contact_person],
                'contact_designation' => ['Designation', fn (Developer $d) => $d->contact_designation],
                'email' => ['Email', fn (Developer $d) => $d->email ?: $d->user?->email],
                'mobile' => ['Mobile', fn (Developer $d) => $d->mobile],
            ],
            'Key contact' => [
                'key_contact_person' => ['Key contact', fn (Developer $d) => $d->key_contact_person],
                'key_contact_designation' => ['Key designation', fn (Developer $d) => $d->key_contact_designation],
                'key_contact_mobile' => ['Key mobile', fn (Developer $d) => $d->key_contact_mobile],
                'key_contact_email' => ['Key email', fn (Developer $d) => $d->key_contact_email],
            ],
            'Location' => [
                'city' => ['City', fn (Developer $d) => $d->city],
                'state' => ['State', fn (Developer $d) => $d->state],
                'country' => ['Country', fn (Developer $d) => $d->country],
                'pincode' => ['Pincode', fn (Developer $d) => $d->pincode],
                'address' => ['Address', fn (Developer $d) => $d->address],
            ],
            'Commercial' => [
                'payout' => ['CP payout %', fn (Developer $d) => $d->cp_payout_percent !== null ? $d->cp_payout_percent . '%' : null],
                'listings' => ['Listings', fn (Developer $d) => $d->properties_count],
                'created' => ['Created', fn (Developer $d) => $d->created_at?->format('Y-m-d')],
            ],
            'Social' => [
                'instagram' => ['Instagram', fn (Developer $d) => $d->instagram],
                'facebook' => ['Facebook', fn (Developer $d) => $d->facebook],
                'youtube' => ['YouTube', fn (Developer $d) => $d->youtube],
                'twitter' => ['Twitter', fn (Developer $d) => $d->twitter],
                'linkedin' => ['LinkedIn', fn (Developer $d) => $d->linkedin],
            ],
        ];
    }

    /**
     * Named starting points for the picker; Select all / Deselect all are always offered too.
     *
     * @return array<string,array<int,string>>
     */
    private function exportTemplates(): array
    {
        return [
            'Standard' => ['company', 'contact_person', 'email', 'mobile', 'city', 'country', 'status', 'listings'],
            'Contact' => ['company', 'contact_person', 'contact_designation', 'email', 'mobile'],
            'Commercial' => ['company', 'payout', 'listings', 'status', 'created'],
        ];
    }

    /** The full record behind a row: company, login, commercial terms and activity. */
    public function show(Developer $developer): View
    {
        $this->authorize('view-module', 'developers');

        $developer->load('user:id,email,status');

        return view('admin.developers.show', [
            'developer' => $developer,
            /*
             * This developer's projects, newest first.
             *
             * Not paginated: the panel sits below the record on a page that already has
             * its own scroll, and a second paginator here would fight the page's own
             * query string. Capped instead — a developer with more than the cap gets a
             * "view all" link into the projects list, filtered to them, which is the
             * screen built for browsing at that size.
             */
            'properties' => $developer->properties()
                ->latest()
                ->limit(self::PROJECTS_ON_PROFILE + 1)
                ->get(),
            'projectCap' => self::PROJECTS_ON_PROFILE,
            'stats' => [
                'listings' => $developer->properties()->count(),
                'active' => $developer->properties()->where('listing_status', 'active')->count(),
                'views' => (int) $developer->properties()->sum('views_count'),
                'leads' => $developer->leads()->count(),
            ],
            // Shown in the delete confirmation — both cascade with the company record.
            'cascades' => [
                'listings' => $developer->properties()->count(),
                'leads' => $developer->leads()->count(),
            ],
        ]);
    }

    /** Creates the company record and its login account in one transaction. */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('edit-module', 'developers');

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255', 'unique:developers,company_name'],
            'contact_person' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // Typed in the form; blank falls back to a generated one. 72 is bcrypt's
            // hard limit — anything longer is silently truncated.
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
            'mobile' => ['required', 'string', 'max:32'],
            'contact_designation' => ['nullable', 'string', 'max:96'],

            // Key contact — internal. Nullable because it is often filled in after the
            // account exists, once someone has actually spoken to the developer.
            'key_contact_person' => ['nullable', 'string', 'max:255'],
            'key_contact_designation' => ['nullable', 'string', 'max:96'],
            'key_contact_mobile' => ['nullable', 'string', 'max:32'],
            'key_contact_email' => ['nullable', 'email', 'max:255'],

            'country' => ['nullable', 'string', 'max:96'],
            'city' => ['required', 'string', 'max:96'],
            'state' => ['nullable', 'string', 'max:96'],
            'pincode' => ['nullable', 'string', 'max:12'],
            'address' => ['nullable', 'string', 'max:1000'],
            // Bounded to real coordinates: a transposed pair puts the developer in the
            // sea, and a stray decimal puts them off the planet.
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'rera_number' => ['nullable', 'string', 'max:64'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'about' => ['nullable', 'string', 'max:5000'],
            'website' => ['nullable', 'string', 'max:255'],
            ...SocialPlatforms::rules(),
            // Commercial terms are no longer asked for at creation — see below. Still
            // validated rather than ignored, so a client that does send them is held to
            // the same bounds as the edit form.
            'cp_payout_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'verified' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:active,paused'],
        ]);

        /**
         * Defaults for the fields the create form no longer collects.
         *
         * `verified` and `status` match the column defaults: a new developer is unverified
         * until someone checks their licence, and active so the account works immediately.
         *
         * `cp_payout_percent` deliberately does NOT use the column default of 0. The form
         * pre-filled 2.50 and most admins accepted it, so falling through to 0 would
         * quietly create developers paying channel partners nothing — a real commercial
         * value dressed up as an empty one. 2.50 keeps the previous outcome for the common
         * path; it is editable on the developer's page straight after creation.
         */
        $data['cp_payout_percent'] ??= 2.50;
        $data['verified'] ??= false;
        $data['status'] ??= 'active';

        // `logo` is the upload; `logo_path` is what the column stores.
        unset($data['logo']);
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('developers/logos', 'public');
        }

        // Credential handed to the developer; they change it on first sign-in. `password` is
        // nullable, so validate() omits the key entirely when it was not submitted — reading
        // it directly would raise an undefined-key warning and 500 the request.
        $password = ($data['password'] ?? '') ?: Str::password(14, symbols: false);
        unset($data['password']);   // lives on the user row, not the developer record

        DB::transaction(function () use ($data, $password) {
            $user = User::create([
                'name' => $data['contact_person'],
                'email' => $data['email'],
                'password' => $password,
                'mobile' => $data['mobile'],
                'role' => User::ROLE_DEVELOPER,
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ]);

            Developer::create($data + ['user_id' => $user->id]);
        });

        // The password rides in its own flash key so x-credentials-dialog can show it
        // with the share options, rather than being spelled out in a toast.
        return redirect()
            ->route('admin.developers')
            ->with('success', "{$data['company_name']} added.")
            ->with('credentials', [
                'name' => $data['contact_person'],
                'email' => $data['email'],
                'password' => $password,
            ]);
    }

    /**
     * A starter CSV — the exact column names {@see bulkImport()} reads, with one sample
     * row so "what goes in this column" never has to be guessed from a blank sheet.
     */
    public function bulkImportTemplate(): Response
    {
        $this->authorize('edit-module', 'developers');

        $columns = [
            'company_name', 'contact_person', 'email', 'mobile', 'city',
            'country', 'state', 'pincode', 'address', 'rera_number', 'about', 'website',
            'instagram', 'facebook', 'youtube', 'twitter', 'linkedin', 'password',
        ];

        $sample = [
            'Skyline Realty Group', 'Ahmed Al Farsi', 'ahmed@skylinerealty.example', '+91 90000 00000', 'Hyderabad',
            'India', 'Telangana', '500032', '', 'RERA/TEL/DEV/00000', '', '',
            '', '', '', '', '', '',
        ];

        $csv = implode(',', $columns) . "\n" . implode(',', array_map(
            fn ($v) => str_contains($v, ',') ? '"' . str_replace('"', '""', $v) . '"' : $v,
            $sample
        )) . "\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="developers-import-template.csv"',
        ]);
    }

    /**
     * One row per developer — everything {@see store()} collects except the logo and the
     * commercial/verification fields an admin sets deliberately, one at a time, on the
     * profile page afterward. No file columns exist in a spreadsheet, so the logo (and
     * any other attachment) is always added by hand post-import — same page, same field,
     * whether the developer arrived one at a time or a hundred at a time.
     *
     * Every row is validated and created independently: one bad row does not block the
     * other 99. The result is reported back per row rather than as a single pass/fail.
     */
    public function bulkImport(Request $request): View
    {
        $this->authorize('edit-module', 'developers');

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [
            'file.required' => 'Choose the filled-in CSV file first.',
            'file.mimes' => 'That is not a CSV file. In Excel or Google Sheets use '
                . '"Save as"/"Download" and pick CSV — an .xlsx is not read directly.',
            'file.max' => 'That file is over 5 MB. Split it into a few smaller sheets and upload them one by one.',
        ]);

        // The columns a developer cannot be created without — checked as a header before
        // any row is read, so the wrong sheet fails once instead of on every row.
        $rows = CsvReader::rows($request->file('file'), [
            'company_name', 'contact_person', 'email', 'mobile', 'city',
        ]);

        $results = [];

        foreach ($rows as $number => $row) {
            $results[] = $this->importDeveloperRow($number, $row);
        }

        return view('admin.bulk-import-result', [
            'type' => 'developers',
            'results' => $results,
            'created' => count(array_filter($results, fn ($r) => $r['status'] === 'created')),
            'failed' => count(array_filter($results, fn ($r) => $r['status'] === 'failed')),
        ]);
    }

    /**
     * Every step below — mapping the row, validating it, saving it — is inside one
     * try/catch, not just the save. A hand-built CSV missing an entire optional
     * column (no `address` header at all, say) used to throw an uncaught "Undefined
     * array key" and take down the whole import with a 500, instead of just failing
     * that one row like every other bad-data case already does.
     *
     * @return array{row: int, status: string, company_name: ?string, email: ?string, password: ?string, error: ?string}
     */
    private function importDeveloperRow(int $number, array $row): array
    {
        $companyName = null;

        try {
            $data = [
                'company_name' => $row['company_name'] ?? '',
                'contact_person' => $row['contact_person'] ?? '',
                'email' => strtolower($row['email'] ?? ''),
                'mobile' => $row['mobile'] ?? '',
                'city' => $row['city'] ?? '',
                'country' => $row['country'] ?? null,
                'state' => $row['state'] ?? null,
                'pincode' => $row['pincode'] ?? null,
                'address' => $row['address'] ?? null,
                'rera_number' => $row['rera_number'] ?? null,
                'about' => $row['about'] ?? null,
                'website' => $row['website'] ?? null,
                'instagram' => $row['instagram'] ?? null,
                'facebook' => $row['facebook'] ?? null,
                'youtube' => $row['youtube'] ?? null,
                'twitter' => $row['twitter'] ?? null,
                'linkedin' => $row['linkedin'] ?? null,
                'password' => $row['password'] ?? null,
            ];

            // Blank optional cells arrive as '' (see CsvReader), which a `nullable` rule
            // treats as present-but-empty rather than absent — normalise once, here,
            // instead of relying on every field below to remember to do it.
            foreach ($data as $key => $value) {
                if ($key !== 'company_name' && $key !== 'contact_person' && $key !== 'email'
                    && $key !== 'mobile' && $key !== 'city' && $value === '') {
                    $data[$key] = null;
                }
            }

            $companyName = $data['company_name'] ?: null;

            $validator = Validator::make($data, [
                'company_name' => ['required', 'string', 'max:255', 'unique:developers,company_name'],
                'contact_person' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'mobile' => ['required', 'string', 'max:32'],
                'city' => ['required', 'string', 'max:96'],
                'country' => ['nullable', 'string', 'max:96'],
                'state' => ['nullable', 'string', 'max:96'],
                'pincode' => ['nullable', 'string', 'max:12'],
                'address' => ['nullable', 'string', 'max:1000'],
                'rera_number' => ['nullable', 'string', 'max:64'],
                'about' => ['nullable', 'string', 'max:5000'],
                'website' => ['nullable', 'string', 'max:255'],
                ...SocialPlatforms::rules(),
                'password' => ['nullable', 'string', 'min:8', 'max:72'],
            ]);

            if ($validator->fails()) {
                return [
                    'row' => $number,
                    'status' => 'failed',
                    'company_name' => $companyName,
                    'email' => null,
                    'password' => null,
                    'error' => implode(' ', $validator->errors()->all()),
                ];
            }

            $clean = $validator->validated();
            $password = $clean['password'] ?: Str::password(14, symbols: false);
            unset($clean['password']);

            DB::transaction(function () use ($clean, $password) {
                $user = User::create([
                    'name' => $clean['contact_person'],
                    'email' => $clean['email'],
                    'password' => $password,
                    'mobile' => $clean['mobile'],
                    'role' => User::ROLE_DEVELOPER,
                    'status' => User::STATUS_ACTIVE,
                    'email_verified_at' => now(),
                ]);

                Developer::create($clean + [
                    'user_id' => $user->id,
                    // Same defaults store() applies for a single create — see the note there.
                    'cp_payout_percent' => 2.50,
                    'verified' => false,
                    'status' => 'active',
                ]);
            });
        } catch (\Throwable $e) {
            return [
                'row' => $number,
                'status' => 'failed',
                'company_name' => $companyName,
                'email' => null,
                'password' => null,
                'error' => 'Could not save this row: ' . $e->getMessage(),
            ];
        }

        return [
            'row' => $number,
            'status' => 'created',
            'company_name' => $clean['company_name'],
            'email' => $clean['email'],
            'password' => $password,
            'error' => null,
        ];
    }

    /**
     * Set a new password on the developer's login account.
     *
     * Stored passwords are hashed, so the existing one can never be re-shown — handing
     * over credentials again means setting a new one. Active API tokens are revoked so
     * the old password stops working immediately.
     */
    public function resetPassword(Request $request, Developer $developer): RedirectResponse
    {
        $this->authorize('edit-module', 'developers');

        abort_unless($developer->user, 404, 'This developer has no login account.');

        $data = $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
        ], [
            'password.min' => 'The password must be at least 8 characters, or leave it blank to generate one.',
            'password.max' => 'The password cannot be longer than 72 characters.',
        ]);

        // `nullable` means validate() drops the key when it was not submitted at all.
        $password = ($data['password'] ?? '') ?: Str::password(14, symbols: false);

        $developer->user->update(['password' => $password]);
        $developer->user->tokens()->delete();

        return redirect()
            ->route('admin.developers')
            ->with('success', "New password issued for {$developer->company_name}.")
            ->with('credentials', [
                'name' => $developer->contact_person,
                'email' => $developer->user->email,
                'password' => $password,
            ]);
    }

    /**
     * Serves both the full Edit form and the row menu's Pause/Reactivate item — every
     * field except `status` is `sometimes`, so the quick action can keep posting just
     * the one field it owns without blanking the rest of the record.
     */
    public function update(Request $request, Developer $developer): RedirectResponse
    {
        $this->authorize('edit-module', 'developers');

        if ($request->has('email')) {
            $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        }

        $data = $request->validate([
            'company_name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('developers', 'company_name')->ignore($developer->id),
            ],
            'contact_person' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes', 'required', 'email:rfc', 'max:255',
                // The login lives on users; developers.email only mirrors it.
                Rule::unique('users', 'email')->ignore($developer->user_id),
            ],
            'mobile' => ['sometimes', 'required', 'string', 'max:32'],
            'contact_designation' => ['sometimes', 'nullable', 'string', 'max:96'],

            'key_contact_person' => ['sometimes', 'nullable', 'string', 'max:255'],
            'key_contact_designation' => ['sometimes', 'nullable', 'string', 'max:96'],
            'key_contact_mobile' => ['sometimes', 'nullable', 'string', 'max:32'],
            'key_contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],

            'country' => ['sometimes', 'nullable', 'string', 'max:96'],
            'city' => ['sometimes', 'required', 'string', 'max:96'],
            'state' => ['sometimes', 'nullable', 'string', 'max:96'],
            'pincode' => ['sometimes', 'nullable', 'string', 'max:12'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],

            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            ...SocialPlatforms::rules(sometimes: true),
            'rera_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'about' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'cp_payout_percent' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'verified' => ['sometimes', 'required', 'boolean'],
            'status' => ['required', 'in:active,paused'],
        ], [
            'company_name.unique' => 'Another developer already uses this company name.',
            'email.unique' => 'An account with this email already exists.',
        ]);

        unset($data['logo']);
        if ($request->hasFile('logo')) {
            $previous = $developer->logo_path;
            $data['logo_path'] = $request->file('logo')->store('developers/logos', 'public');

            // Only after the replacement is safely stored.
            if ($previous) {
                Storage::disk('public')->delete($previous);
            }
        }

        DB::transaction(function () use ($developer, $data) {
            $developer->update($data);

            // Keep the login account in step — the email here IS their username.
            $developer->user?->update(array_filter([
                'name' => $data['contact_person'] ?? null,
                'email' => $data['email'] ?? null,
                'mobile' => $data['mobile'] ?? null,
            ]));
        });

        return back()->with('success', "{$developer->company_name} updated.");
    }

    /**
     * Deleting the company takes its listings and leads with it — both tables cascade
     * on developers.id — and the login account is removed alongside it. The confirm
     * dialog spells out those counts before this is ever reached.
     */
    public function destroy(Developer $developer): RedirectResponse
    {
        $this->authorize('edit-module', 'developers');

        $name = $developer->company_name;
        $logo = $developer->logo_path;

        DB::transaction(function () use ($developer) {
            $user = $developer->user;

            // Developer first: developers.user_id is ON DELETE SET NULL, so removing the
            // user first would detach the row and orphan it instead of cascading.
            $developer->delete();

            $user?->tokens()->delete();
            $user?->delete();
        });

        if ($logo) {
            Storage::disk('public')->delete($logo);
        }

        return redirect()
            ->route('admin.developers')
            ->with('warning', "{$name} and all of its listings were deleted.");
    }
}
