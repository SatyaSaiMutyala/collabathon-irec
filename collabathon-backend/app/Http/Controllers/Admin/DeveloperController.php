<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\Developer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DeveloperController extends Controller
{
    use HandlesListQueries;

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

    public function index(Request $request): View
    {
        $query = Developer::query()
            ->withCount('properties')
            ->when($request->query('search'), function ($q, $term) {
                // Prefix match so the company_name index is usable.
                $q->where(fn ($w) => $w->where('company_name', 'like', $term . '%')
                    ->orWhere('contact_person', 'like', $term . '%')
                    ->orWhere('email', 'like', $term . '%'));
            })
            ->when($request->query('city'), fn ($q, $v) => $q->where('city', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v));

        $query = $this->applySort($query, $request, self::SORTABLE);

        return view('admin.developers', [
            'developers' => $this->paginate($query, $request),
            'cities' => Developer::query()->distinct()->orderBy('city')->pluck('city')->filter()->values(),
            'totals' => [
                'all' => Developer::count(),
                'active' => Developer::where('status', 'active')->count(),
                'listings' => DB::table('properties')->whereNull('deleted_at')->count(),
                'avg_payout' => round((float) Developer::avg('cp_payout_percent'), 2),
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
            'mobile' => ['required', 'string', 'max:32'],
            'city' => ['required', 'string', 'max:96'],
            'cp_payout_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'in:active,paused'],
        ]);

        // Temporary credential handed to the developer; they change it on first sign-in.
        $tempPassword = Str::password(12, symbols: false);

        DB::transaction(function () use ($data, $tempPassword) {
            $user = User::create([
                'name' => $data['contact_person'],
                'email' => $data['email'],
                'password' => $tempPassword,
                'mobile' => $data['mobile'],
                'role' => User::ROLE_DEVELOPER,
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ]);

            Developer::create($data + ['user_id' => $user->id]);
        });

        return back()->with('status', "Developer created. Temporary password: {$tempPassword}");
    }

    public function update(Request $request, Developer $developer): RedirectResponse
    {
        $this->authorize('edit-module', 'developers');

        $data = $request->validate([
            'status' => ['required', 'in:active,paused'],
        ]);

        $developer->update($data);

        return back()->with('status', 'Developer updated.');
    }
}
