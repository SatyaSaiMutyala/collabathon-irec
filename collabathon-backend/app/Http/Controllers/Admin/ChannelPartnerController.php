<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\ExportsList;
use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\ApprovalDecision;
use App\Models\BrokerProfile;
use App\Models\Lead;
use App\Models\User;
use App\Support\CsvReader;
use App\Support\SocialPlatforms;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Channel Partners — the brokers who are already through approval and working.
 *
 * Deliberately separate from the approvals queue rather than a fourth tab on it. That
 * screen exists to empty a backlog and is read newest-first; this one is a directory of
 * an active roster that only grows, and is searched rather than worked through.
 */
class ChannelPartnerController extends Controller
{
    use HandlesListQueries, ExportsList;

    /** Public filter name => real column, for the sort whitelist. */
    private const SORTABLE = [
        'created_at' => 'users.created_at',
        'name' => 'users.name',
        'city' => 'broker_profiles.city',
    ];

    /** Enough to fill a select; past this the search box is the right tool. */
    private const MAX_OPTIONS = 60;

    protected function defaultPerPage(): int
    {
        return 15;
    }

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\Response
    {
        // Active and inactive both belong on this roster — inactive is a broker who
        // deleted their own account (see AuthController::deleteAccount), not one who
        // was never approved, so it stays a visible row here rather than dropping out
        // the way a pending/rejected one correctly does. Stats and filter option lists
        // further down stay scoped to active only — an inactive broker shouldn't
        // inflate "Active partners" or offer a filter that returns a dead account.
        $partners = User::role(User::ROLE_BROKER)
            ->whereIn('status', [User::STATUS_ACTIVE, User::STATUS_INACTIVE])
            ->select('users.*')
            // Joined rather than filtered through whereHas so city and name can both be
            // sorted on, and so a broker with no profile row never disappears silently.
            ->leftJoin('broker_profiles', 'broker_profiles.user_id', '=', 'users.id')
            ->with('brokerProfile')
            // Counted here rather than per row: the roster is the whole point of the page,
            // and one aggregate beats a query per partner.
            ->withCount(['leads as accepted_leads_count' => fn ($q) => $q
                ->where('status', Lead::STATUS_ACCEPTED)])
            ->when($request->query('search'), function ($q, $term) {
                $q->where(fn ($w) => $w
                    ->where('users.name', 'like', $term . '%')
                    ->orWhere('users.email', 'like', $term . '%')
                    ->orWhere('users.mobile', 'like', $term . '%')
                    ->orWhere('broker_profiles.company_name', 'like', $term . '%')
                    ->orWhere('broker_profiles.rera_number', 'like', $term . '%'));
            })
            ->when($request->query('city'), fn ($q, $v) => $q->where('broker_profiles.city', $v))
            ->when($request->query('state'), fn ($q, $v) => $q->where('broker_profiles.state', $v))
            // segments/zones are JSON arrays, so this is a containment test, not equality.
            ->when($request->query('segment'), fn ($q, $v) => $q
                ->whereJsonContains('broker_profiles.segments', $v))
            ->when($request->query('type'), fn ($q, $v) => $q
                ->where('broker_profiles.is_company', $v === 'company'));

        $partners = $this->applySort($partners, $request, self::SORTABLE);

        $grouped = $this->exportColumns();

        if ($format = $this->exportFormat($request)) {
            return $this->exportList($format, $partners, 'channel-partners', 'Channel Partners',
                $this->flattenGroupedColumns($grouped), $request);
        }

        return view('admin.cp', [
            'partners' => $partners->paginate($this->perPage($request))->withQueryString(),
            'cities' => $this->distinctProfileValues('city'),
            'states' => $this->distinctProfileValues('state'),
            'segments' => $this->distinctSegments(),
            'stats' => $this->stats(),
            'export' => [
                'groups' => $this->exportGroupLabels($grouped),
                'templates' => $this->exportTemplates(),
            ],
        ]);
    }

    /**
     * Exportable columns, grouped for the picker: group label => key => [label, getter].
     * The key is what the picker's checkboxes send back; the getter reads one cell off a
     * partner. The grouping is UI only — {@see \App\Http\Concerns\ExportsList} flattens it
     * for the actual export, in this definition order.
     *
     * @return array<string,array<string,array{0:string,1:callable}>>
     */
    private function exportColumns(): array
    {
        return [
            'Account' => [
                'reg_id' => ['Registration ID', fn (User $p) => $p->id],
                'name' => ['Name', fn (User $p) => $p->name],
                'status' => ['Status', fn (User $p) => ucfirst((string) $p->status)],
                'joined' => ['Joined', fn (User $p) => $p->created_at?->format('Y-m-d')],
            ],
            'Contact' => [
                'email' => ['Email', fn (User $p) => $p->email],
                'mobile' => ['Mobile', fn (User $p) => $p->mobile],
                'alternate_mobile' => ['Alternate mobile', fn (User $p) => $p->brokerProfile?->alternate_mobile],
                'residence_address' => ['Residence address', fn (User $p) => $p->brokerProfile?->residence_address],
            ],
            'Business' => [
                'type' => ['Type', fn (User $p) => $this->companyType($p->brokerProfile?->is_company)],
                'company_name' => ['Company name', fn (User $p) => $p->brokerProfile?->company_name],
                'company_website' => ['Company website', fn (User $p) => $p->brokerProfile?->company_website],
                'office_address' => ['Office address', fn (User $p) => $p->brokerProfile?->office_address],
                'years_of_experience' => ['Experience (yrs)', fn (User $p) => $p->brokerProfile?->years_of_experience],
                'team_size' => ['Team size', fn (User $p) => $p->brokerProfile?->team_size],
                'segments' => ['Segments', fn (User $p) => $p->brokerProfile?->segments],
                'zones' => ['Zones', fn (User $p) => $p->brokerProfile?->zones],
            ],
            'Location' => [
                'city' => ['City', fn (User $p) => $p->brokerProfile?->city],
                'state' => ['State', fn (User $p) => $p->brokerProfile?->state],
                'operates_multiple_states' => ['Multi-state', fn (User $p) => $this->yesNo($p->brokerProfile?->operates_multiple_states)],
            ],
            'KYC & compliance' => [
                'rera_number' => ['RERA number', fn (User $p) => $p->brokerProfile?->rera_number],
                'rera_certificate_expiry' => ['RERA expiry', fn (User $p) => $p->brokerProfile?->rera_certificate_expiry?->format('Y-m-d')],
                'pan_card' => ['PAN', fn (User $p) => $p->brokerProfile?->pan_card],
                'aadhaar_card' => ['Aadhaar', fn (User $p) => $p->brokerProfile?->aadhaar_card],
                'aadhaar_verified' => ['Aadhaar verified', fn (User $p) => $this->yesNo($p->brokerProfile?->aadhaar_verified)],
                'gst_number' => ['GST', fn (User $p) => $p->brokerProfile?->gst_number],
            ],
            'Social' => [
                'instagram' => ['Instagram', fn (User $p) => $p->brokerProfile?->instagram],
                'facebook' => ['Facebook', fn (User $p) => $p->brokerProfile?->facebook],
                'youtube' => ['YouTube', fn (User $p) => $p->brokerProfile?->youtube],
                'twitter' => ['Twitter', fn (User $p) => $p->brokerProfile?->twitter],
                'linkedin' => ['LinkedIn', fn (User $p) => $p->brokerProfile?->linkedin],
            ],
            'Activity' => [
                'accepted_leads' => ['Accepted leads', fn (User $p) => $p->accepted_leads_count],
                'project_contributions' => ['Project contributions', fn (User $p) => $p->brokerProfile?->project_contributions],
                'submitted_at' => ['Submitted at', fn (User $p) => $p->brokerProfile?->submitted_at?->format('Y-m-d')],
            ],
        ];
    }

    /**
     * Named starting points for the picker: template name => the keys it ticks. The picker
     * always also offers Select all / Deselect all, so these are the in-between shortcuts.
     *
     * @return array<string,array<int,string>>
     */
    private function exportTemplates(): array
    {
        return [
            'Standard' => ['reg_id', 'name', 'email', 'mobile', 'city', 'state', 'status', 'joined'],
            'Contact' => ['name', 'email', 'mobile', 'alternate_mobile', 'residence_address'],
            'Business' => ['name', 'type', 'company_name', 'segments', 'zones', 'years_of_experience', 'team_size'],
            'KYC' => ['name', 'rera_number', 'rera_certificate_expiry', 'pan_card', 'aadhaar_card', 'aadhaar_verified', 'gst_number'],
        ];
    }

    /** Yes/No/blank — a tri-state so a never-answered flag exports empty, not "No". */
    private function yesNo(?bool $value): ?string
    {
        return $value === null ? null : ($value ? 'Yes' : 'No');
    }

    /** The is_company flag as a word, blank when the profile never set it. */
    private function companyType(?bool $isCompany): ?string
    {
        return $isCompany === null ? null : ($isCompany ? 'Company' : 'Individual');
    }

    /**
     * A starter CSV — the exact column names {@see bulkImport()} reads, with one sample
     * row so "what goes in this column" never has to be guessed from a blank sheet.
     *
     * Same shape as the developers and listings templates. The multi-value columns
     * (`segments`, `zones`) hold a `|`-separated list because the file itself is
     * comma-delimited: a quoted cell would survive the parse, but only until someone
     * edits it in Notepad and drops the quotes.
     */
    public function bulkImportTemplate(): Response
    {
        $this->authorize('edit-module', 'cp');

        $columns = [
            'name', 'email', 'mobile', 'alternate_mobile', 'is_company', 'company_name',
            'city', 'state', 'zones', 'segments',
            'rera_number', 'rera_certificate_expiry', 'years_of_experience', 'team_size',
            'pan_card', 'aadhaar_card', 'gst_number',
            'residence_address', 'office_address', 'company_website',
            'instagram', 'facebook', 'youtube', 'twitter', 'linkedin',
            'project_contributions', 'password',
        ];

        $sample = [
            'Rahul Verma', 'rahul@vermaproperties.example', '9000000000', '', 'yes', 'Verma Properties',
            'Hyderabad', 'Telangana', 'Kokapet|Gachibowli', 'Residential|Commercial',
            'RERA/TEL/AGT/00000', '2028-03-31', '6', '4',
            '', '', '',
            '', '', '',
            '', '', '', '', '',
            '', '',
        ];

        $csv = implode(',', $columns) . "\n" . implode(',', array_map(
            fn ($v) => str_contains($v, ',') ? '"' . str_replace('"', '""', $v) . '"' : $v,
            $sample
        )) . "\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="channel-partners-import-template.csv"',
        ]);
    }

    /**
     * One row per channel partner — the registration fields that fit in a spreadsheet.
     *
     * Scans (photo, PAN, Aadhaar, RERA certificate, cheque, signature) have no column
     * because a CSV cannot carry a file; those stay a per-partner upload on the review
     * page afterward, exactly as they do for a developer's logo.
     *
     * Rows land *active*, not pending: an admin uploading a roster has already vetted it,
     * and routing a sheet of a hundred partners back through the approvals queue for the
     * same admin to click approve a hundred times would be busywork. Each one still gets
     * an ApprovalDecision so the audit trail reads the same as one approved by hand.
     *
     * Every row is validated and created independently: one bad row does not block the
     * other 99. The result is reported back per row rather than as a single pass/fail.
     */
    public function bulkImport(Request $request): View
    {
        $this->authorize('edit-module', 'cp');

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [
            'file.required' => 'Choose the filled-in CSV file first.',
            'file.mimes' => 'That is not a CSV file. In Excel or Google Sheets use '
                . '"Save as"/"Download" and pick CSV — an .xlsx is not read directly.',
            'file.max' => 'That file is over 5 MB. Split it into a few smaller sheets and upload them one by one.',
        ]);

        // Only the three columns a partner cannot be created without. Every other column
        // may be missing from the sheet entirely, and every other *cell* may be blank —
        // see the rules in importPartnerRow().
        $rows = CsvReader::rows($request->file('file'), ['name', 'email', 'mobile']);

        $results = [];

        foreach ($rows as $number => $row) {
            $results[] = $this->importPartnerRow($number, $row, $request->user()->id);
        }

        return view('admin.bulk-import-result', [
            'type' => 'cp',
            'label' => 'channel partners',
            'results' => $results,
            'created' => count(array_filter($results, fn ($r) => $r['status'] === 'created')),
            'failed' => count(array_filter($results, fn ($r) => $r['status'] === 'failed')),
        ]);
    }

    /**
     * Every step below — mapping the row, validating it, saving it — is inside one
     * try/catch, not just the save, for the same reason as
     * {@see DeveloperController::importDeveloperRow()}: a hand-built CSV missing an
     * optional column entirely should fail that one row, not 500 the whole import.
     *
     * @return array{row: int, status: string, name: ?string, email: ?string, password: ?string, partner_id: ?int, error: ?string}
     */
    private function importPartnerRow(int $number, array $row, int $decidedBy): array
    {
        $name = null;

        try {
            $name = trim((string) ($row['name'] ?? '')) ?: null;

            $data = [
                'name' => $row['name'] ?? '',
                'email' => strtolower(trim((string) ($row['email'] ?? ''))),
                'mobile' => self::normaliseMobile($row['mobile'] ?? ''),
                'alternate_mobile' => self::normaliseMobile($row['alternate_mobile'] ?? '') ?: null,
                'company_name' => self::text($row['company_name'] ?? null),
                'city' => self::text($row['city'] ?? null),
                'state' => self::text($row['state'] ?? null),
                'zones' => self::toList($row['zones'] ?? ''),
                'segments' => self::toList($row['segments'] ?? ''),
                'rera_number' => self::text($row['rera_number'] ?? null),
                'rera_certificate_expiry' => self::text($row['rera_certificate_expiry'] ?? null),
                'years_of_experience' => self::text($row['years_of_experience'] ?? null),
                'team_size' => self::text($row['team_size'] ?? null),
                'pan_card' => self::text($row['pan_card'] ?? null),
                'aadhaar_card' => self::text($row['aadhaar_card'] ?? null),
                'gst_number' => self::text($row['gst_number'] ?? null),
                'residence_address' => self::text($row['residence_address'] ?? null),
                'office_address' => self::text($row['office_address'] ?? null),
                'company_website' => self::text($row['company_website'] ?? null),
                'instagram' => self::text($row['instagram'] ?? null),
                'facebook' => self::text($row['facebook'] ?? null),
                'youtube' => self::text($row['youtube'] ?? null),
                'twitter' => self::text($row['twitter'] ?? null),
                'linkedin' => self::text($row['linkedin'] ?? null),
                'project_contributions' => self::text($row['project_contributions'] ?? null),
                'password' => self::text($row['password'] ?? null),
            ];

            // A sheet that fills in a company name and leaves the flag blank means a
            // company — the two columns say the same thing, and only one of them is
            // something a person remembers to fill in.
            $companyFlag = self::text($row['is_company'] ?? null);
            $isCompany = self::toBool($companyFlag);

            // Anything else in that cell ("maybe", "1 person", a stray date) is not
            // silently read as "no": a partner filed as an individual because of a typo
            // looks correct on the roster, which is worse than a row that failed.
            if ($companyFlag !== null && $isCompany === null) {
                return $this->failedRow($number, $name, "The is_company column must be yes or no — got \"{$companyFlag}\".");
            }

            $data['is_company'] = $isCompany ?? ($data['company_name'] !== null);

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                // digits:10, not the free-form max:32 the profile column allows: OTP
                // sign-in looks the account up by an exact 10-digit match
                // (AuthController::verifyOtp), so a partner imported as "+91 90000 00000"
                // would own a row nobody could ever sign in to. normaliseMobile() strips
                // the punctuation and country code first — this catches what is left.
                'mobile' => ['required', 'digits:10', 'unique:users,mobile'],
                'alternate_mobile' => ['nullable', 'digits:10'],
                'is_company' => ['boolean'],
                'company_name' => ['nullable', 'string', 'max:255'],
                'city' => ['nullable', 'string', 'max:96'],
                'state' => ['nullable', 'string', 'max:96'],
                'zones' => ['array'],
                'zones.*' => ['string', 'max:96'],
                'segments' => ['array'],
                'segments.*' => ['string', 'max:64'],
                'rera_number' => ['nullable', 'string', 'max:64'],
                'rera_certificate_expiry' => ['nullable', 'date'],
                'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
                'team_size' => ['nullable', 'integer', 'min:0', 'max:10000'],
                'pan_card' => ['nullable', 'string', 'max:32'],
                'aadhaar_card' => ['nullable', 'string', 'max:32'],
                'gst_number' => ['nullable', 'string', 'max:32'],
                'residence_address' => ['nullable', 'string', 'max:1000'],
                'office_address' => ['nullable', 'string', 'max:1000'],
                'company_website' => ['nullable', 'string', 'max:255'],
                ...SocialPlatforms::rules(),
                'project_contributions' => ['nullable', 'string', 'max:5000'],
                'password' => ['nullable', 'string', 'min:8', 'max:72'],
            ], [
                // Wording aimed at whoever is looking at the spreadsheet: what is wrong,
                // in which column, and what to put there instead. The defaults ("The
                // mobile field must be 10 digits") say the first half only.
                'name.required' => 'The name column is empty — every partner needs a contact name.',
                'email.required' => 'The email column is empty — the login account is created from it.',
                'email.email' => 'That email address is not a valid one.',
                'email.unique' => 'An account already exists with this email address.',
                'mobile.required' => 'The mobile column is empty — partners sign in with an OTP sent to it.',
                'mobile.digits' => 'The mobile number must be 10 digits (a +91 or 0 prefix is fine — it is stripped).',
                'mobile.unique' => 'Another account already uses this mobile number.',
                'alternate_mobile.digits' => 'The alternate_mobile must be 10 digits, or left blank.',
                'rera_certificate_expiry.date' => 'The rera_certificate_expiry must be a date like 2028-03-31.',
                'years_of_experience.integer' => 'The years_of_experience must be a plain number like 6, or left blank.',
                'team_size.integer' => 'The team_size must be a plain number like 4, or left blank.',
                'password.min' => 'A password in the sheet must be at least 8 characters — '
                    . 'leave the cell blank to have one generated.',
            ], self::columnNames());

            if ($validator->fails()) {
                return $this->failedRow($number, $name, implode(' ', $validator->errors()->all()));
            }

            $clean = $validator->validated();
            $password = $clean['password'] ?: Str::password(14, symbols: false);
            unset($clean['password']);

            $user = DB::transaction(function () use ($clean, $password, $decidedBy) {
                $user = User::create([
                    'name' => $clean['name'],
                    'email' => $clean['email'],
                    'password' => $password,
                    'mobile' => $clean['mobile'],
                    'role' => User::ROLE_BROKER,
                    'status' => User::STATUS_ACTIVE,
                ]);

                BrokerProfile::create(collect($clean)->only([
                    'alternate_mobile', 'residence_address', 'is_company', 'company_name',
                    'office_address', 'company_website',
                    ...array_keys(SocialPlatforms::ALL),
                    'years_of_experience', 'team_size', 'pan_card', 'aadhaar_card',
                    'rera_number', 'rera_certificate_expiry', 'gst_number',
                    'state', 'city', 'segments', 'zones', 'project_contributions',
                ])->merge([
                    'user_id' => $user->id,
                    // The declaration is the partner's own signature on their own
                    // submission — an admin uploading a sheet has not given it, so this
                    // stays false rather than claiming a confirmation nobody made.
                    'confirm_accuracy' => false,
                    'submitted_at' => now(),
                ])->all());

                ApprovalDecision::create([
                    'user_id' => $user->id,
                    'decided_by' => $decidedBy,
                    'decision' => 'approved',
                    'internal_note' => 'Created via bulk upload.',
                ]);

                return $user;
            });
        } catch (\Throwable $e) {
            // Reported as well as shown: the message on the result page is all the admin
            // gets, and "SQLSTATE[22001]" on its own is not something to debug from.
            report($e);

            return $this->failedRow($number, $name, 'This row could not be saved: ' . $e->getMessage());
        }

        return [
            'row' => $number,
            'status' => 'created',
            'name' => $clean['name'],
            'email' => $clean['email'],
            'password' => $password,
            'partner_id' => $user->id,
            'error' => null,
        ];
    }

    /**
     * One shape for every way a row can fail, so the result page never has to cope with a
     * half-filled row and a new failure path cannot forget a key.
     *
     * @return array{row: int, status: string, name: ?string, email: ?string, password: ?string, partner_id: ?int, error: string}
     */
    private function failedRow(int $number, ?string $name, string $error): array
    {
        return [
            'row' => $number,
            'status' => 'failed',
            'name' => $name,
            'email' => null,
            'password' => null,
            'partner_id' => null,
            'error' => $error,
        ];
    }

    /**
     * Field names as validation messages should print them: the CSV column, verbatim.
     *
     * Laravel would render `rera_certificate_expiry` as "rera certificate expiry", which
     * reads better in a sentence but is not something you can search the header row for.
     *
     * @return array<string,string>
     */
    private static function columnNames(): array
    {
        $columns = [
            'name', 'email', 'mobile', 'alternate_mobile', 'is_company', 'company_name',
            'city', 'state', 'zones', 'segments',
            'rera_number', 'rera_certificate_expiry', 'years_of_experience', 'team_size',
            'pan_card', 'aadhaar_card', 'gst_number',
            'residence_address', 'office_address', 'company_website',
            ...array_keys(SocialPlatforms::ALL),
            'project_contributions', 'password',
        ];

        return array_combine($columns, $columns);
    }

    /**
     * Blank optional cells arrive as '' (see CsvReader), which a `nullable` rule reads as
     * present-but-empty rather than absent — normalised once here instead of relying on
     * two dozen fields to each remember to do it.
     */
    private static function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * The same number gets typed three ways in one sheet — "+91 90000 00000",
     * "09000000000", "9000000000". Everything but the last ten digits is punctuation or a
     * country code, and sign-in matches on those ten.
     */
    private static function normaliseMobile(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    /**
     * `|` is the documented separator (the file is comma-delimited); `;` is accepted
     * because it is what a European Excel locale produces from the same cell.
     *
     * @return array<int,string>
     */
    private static function toList(?string $value): array
    {
        return collect(preg_split('/[|;]/', (string) $value) ?: [])
            ->map(fn ($v) => trim($v))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Spreadsheet truthiness: yes/no, true/false, y/n, 1/0.
     *
     * null means "no answer here" — a blank cell *or* something this doesn't recognise.
     * The caller separates the two; both are wrong to guess at.
     */
    private static function toBool(?string $value): ?bool
    {
        $value = strtolower(trim((string) $value));

        if (in_array($value, ['yes', 'y', 'true', '1'], true)) {
            return true;
        }

        if (in_array($value, ['no', 'n', 'false', '0'], true)) {
            return false;
        }

        return null;
    }

    /**
     * Only values that belong to an *active* broker: offering a city whose only broker is
     * still pending would return an empty table from a filter the page itself suggested.
     */
    private function distinctProfileValues(string $column)
    {
        return BrokerProfile::query()
            ->join('users', 'users.id', '=', 'broker_profiles.user_id')
            ->where('users.role', User::ROLE_BROKER)
            ->where('users.status', User::STATUS_ACTIVE)
            ->whereNotNull("broker_profiles.{$column}")
            ->where("broker_profiles.{$column}", '!=', '')
            ->distinct()
            ->orderBy("broker_profiles.{$column}")
            ->limit(self::MAX_OPTIONS)
            ->pluck("broker_profiles.{$column}");
    }

    /** Flattened out of the JSON column, so the select lists segments and not arrays. */
    private function distinctSegments()
    {
        return BrokerProfile::query()
            ->join('users', 'users.id', '=', 'broker_profiles.user_id')
            ->where('users.role', User::ROLE_BROKER)
            ->where('users.status', User::STATUS_ACTIVE)
            ->whereNotNull('broker_profiles.segments')
            ->limit(self::MAX_OPTIONS * 10)
            ->pluck('broker_profiles.segments')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->take(self::MAX_OPTIONS)
            ->values();
    }

    private function stats(): array
    {
        $active = User::role(User::ROLE_BROKER)->status(User::STATUS_ACTIVE);

        return [
            'total' => (clone $active)->count(),
            'companies' => (clone $active)
                ->whereHas('brokerProfile', fn ($q) => $q->where('is_company', true))->count(),
            'joined_30d' => (clone $active)->where('users.created_at', '>=', now()->subDays(30))->count(),
            'cities' => $this->distinctProfileValues('city')->count(),
        ];
    }
}
