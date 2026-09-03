<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Announcement;
use App\Models\City;
use App\Models\Country;
use App\Models\Developer;
use App\Models\MeasurementUnit;
use App\Models\ProjectType;
use App\Models\Property;
use App\Models\Role;
use App\Models\State;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Everything an admin has moved to Trash — one unified, filterable list across every
 * trashable model, rather than a separate "deleted" tab bolted onto each page. A row
 * here can be restored (undoes the move, nothing was ever actually destroyed) or
 * permanently deleted (irreversible — see each model controller's forceDelete()).
 *
 * Every model is fetched and normalised to the same shape ({@see row()}) so the view
 * renders one consistent table instead of a dozen differently-shaped panels — the
 * "type" a row belongs to is metadata on it, not a reason to structure the page
 * differently per type.
 */
class TrashController extends Controller
{
    /** Per type, so one noisy category can't crowd the rest off the page. */
    private const LIMIT_PER_TYPE = 50;

    /** type key => [icon, singular label], drives both the filter chips and the rows. */
    private const TYPES = [
        'developers' => ['building', 'Developer'],
        'properties' => ['list', 'Listing'],
        'team' => ['users', 'Team member'],
        'cp' => ['user-check', 'Channel partner'],
        'approvals' => ['clock', 'Registration'],
        'amenities' => ['sparkles', 'Amenity'],
        'measurement-units' => ['sparkles', 'Measurement unit'],
        'project-types' => ['sparkles', 'Project type'],
        'unit-types' => ['sparkles', 'Unit type'],
        'announcements' => ['bell', 'Announcement'],
        'countries' => ['map-pin', 'Country'],
        'states' => ['map-pin', 'State'],
        'cities' => ['map-pin', 'City'],
        'roles' => ['shield', 'Role'],
    ];

    public function index(Request $request): View
    {
        $rows = $this->allRows();

        $type = $request->query('type');
        if ($type && isset(self::TYPES[$type])) {
            $rows = $rows->where('type', $type);
        }

        if ($search = trim((string) $request->query('search'))) {
            $rows = $rows->filter(fn (array $row) => str_contains(
                strtolower($row['name'] . ' ' . $row['subtitle']),
                strtolower($search)
            ));
        }

        return view('admin.trash', [
            'rows' => $rows->sortByDesc('deleted_at')->values(),
            // Counts from the *unfiltered* set, so a chip's number never changes
            // depending on which chip is currently selected.
            'counts' => $this->allRows()->countBy('type'),
            'types' => self::TYPES,
            'activeType' => $type,
            'search' => $request->query('search'),
        ]);
    }

    /** Every trashed row, across every model, in one flat normalised collection. */
    private function allRows(): Collection
    {
        return collect([
            ...$this->developers(),
            ...$this->properties(),
            ...$this->team(),
            ...$this->channelPartners(),
            ...$this->approvals(),
            ...$this->simple(Amenity::class, 'amenities'),
            ...$this->simple(MeasurementUnit::class, 'measurement-units'),
            ...$this->simple(ProjectType::class, 'project-types'),
            ...$this->simple(UnitType::class, 'unit-types'),
            ...$this->announcements(),
            ...$this->countries(),
            ...$this->states(),
            ...$this->cities(),
            ...$this->roles(),
        ]);
    }

    private function developers(): array
    {
        return Developer::onlyTrashed()
            ->with('user:id,email')
            ->withCount('properties')
            ->latest('deleted_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn (Developer $dev) => $this->row(
                type: 'developers',
                id: $dev->id,
                name: $dev->company_name,
                subtitle: ($dev->user?->email ?: '—') . ' · ' . $dev->properties_count
                    . ' ' . \Illuminate\Support\Str::plural('listing', $dev->properties_count),
                avatar: $dev->logo_path,
                deletedAt: $dev->deleted_at,
                restoreRoute: route('admin.trash.developers.restore', $dev->id),
                forceDeleteRoute: route('admin.trash.developers.destroy', $dev->id),
                forceDeleteMessage: "{$dev->company_name}, its login account and every one of its "
                    . $dev->properties_count . ' ' . \Illuminate\Support\Str::plural('listing', $dev->properties_count)
                    . ' — including all files — will be permanently deleted. This cannot be undone.',
            ))
            ->all();
    }

    private function properties(): array
    {
        return Property::onlyTrashed()
            ->with(['developer' => fn ($q) => $q->withTrashed()->select('id', 'company_name')])
            ->latest('deleted_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn (Property $property) => $this->row(
                type: 'properties',
                id: $property->id,
                name: $property->name,
                subtitle: $property->developer?->company_name ?: '—',
                avatar: $property->logo_path,
                deletedAt: $property->deleted_at,
                restoreRoute: route('admin.trash.properties.restore', $property->id),
                forceDeleteRoute: route('admin.trash.properties.destroy', $property->id),
                forceDeleteMessage: "\"{$property->name}\" and every file it owns — gallery, brochure, "
                    . 'floor plans, legal documents — will be permanently deleted. This cannot be undone.',
            ))
            ->all();
    }

    private function team(): array
    {
        return User::role(User::ROLE_ADMIN)
            ->whereNotNull('deleted_at')
            ->with('adminRole')
            ->orderByDesc('deleted_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn (User $user) => $this->row(
                type: 'team',
                id: $user->id,
                name: $user->name,
                subtitle: $user->email . ' · ' . ($user->adminRole?->name ?: 'No role'),
                avatar: $user->avatar_path,
                deletedAt: $user->deleted_at,
                restoreRoute: route('admin.trash.team.restore', $user->id),
                forceDeleteRoute: route('admin.trash.team.destroy', $user->id),
                forceDeleteMessage: "{$user->name}'s admin account will be permanently deleted. This cannot be undone.",
            ))
            ->all();
    }

    /** Trashed brokers who were on the CP roster (active or self-deleted-inactive) — see approvals() for the rest. */
    private function channelPartners(): array
    {
        return User::role(User::ROLE_BROKER)
            ->whereIn('status', [User::STATUS_ACTIVE, User::STATUS_INACTIVE])
            ->whereNotNull('deleted_at')
            ->with('brokerProfile:id,user_id,company_name,city,photo_path')
            ->orderByDesc('deleted_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn (User $user) => $this->row(
                type: 'cp',
                id: $user->id,
                name: $user->name,
                subtitle: ($user->brokerProfile?->company_name ?: 'Individual')
                    . ($user->brokerProfile?->city ? " · {$user->brokerProfile->city}" : ''),
                avatar: $user->brokerProfile?->photo_path,
                deletedAt: $user->deleted_at,
                restoreRoute: route('admin.trash.cp.restore', $user->id),
                forceDeleteRoute: route('admin.trash.cp.destroy', $user->id),
                forceDeleteMessage: "{$user->name}'s account, profile and every document (PAN, Aadhaar, RERA, "
                    . 'GST, cheque) will be permanently deleted. This cannot be undone.',
            ))
            ->all();
    }

    /** Anything still `pending`/`rejected`/`draft` and trashed — not yet on the CP roster. */
    private function approvals(): array
    {
        return User::role(User::ROLE_BROKER)
            ->whereIn('status', [User::STATUS_PENDING, User::STATUS_REJECTED, User::STATUS_DRAFT])
            ->whereNotNull('deleted_at')
            ->with('brokerProfile:id,user_id,company_name,city,photo_path')
            ->orderByDesc('deleted_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn (User $user) => $this->row(
                type: 'approvals',
                id: $user->id,
                name: $user->name,
                subtitle: ucfirst($user->status) . ' · ' . ($user->brokerProfile?->company_name ?: 'Individual'),
                avatar: $user->brokerProfile?->photo_path,
                deletedAt: $user->deleted_at,
                restoreRoute: route('admin.trash.approvals.restore', $user->id),
                forceDeleteRoute: route('admin.trash.approvals.destroy', $user->id),
                forceDeleteMessage: "{$user->name}'s registration and documents will be permanently deleted. This cannot be undone.",
            ))
            ->all();
    }

    /** Amenity/MeasurementUnit/ProjectType/UnitType — same shape, same simple destroy. */
    private function simple(string $modelClass, string $type): array
    {
        [, $label] = self::TYPES[$type];

        return $modelClass::onlyTrashed()
            ->latest('deleted_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn ($model) => $this->row(
                type: $type,
                id: $model->id,
                name: $model->name,
                subtitle: $label,
                avatar: null,
                deletedAt: $model->deleted_at,
                restoreRoute: route("admin.trash.{$type}.restore", $model->id),
                forceDeleteRoute: route("admin.trash.{$type}.destroy", $model->id),
                forceDeleteMessage: "\"{$model->name}\" will be permanently deleted. This cannot be undone.",
            ))
            ->all();
    }

    private function announcements(): array
    {
        return Announcement::onlyTrashed()
            ->latest('deleted_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn (Announcement $a) => $this->row(
                type: 'announcements',
                id: $a->id,
                name: $a->title,
                subtitle: $a->audienceLabel(),
                avatar: $a->image_path,
                deletedAt: $a->deleted_at,
                restoreRoute: route('admin.trash.announcements.restore', $a->id),
                forceDeleteRoute: route('admin.trash.announcements.destroy', $a->id),
                forceDeleteMessage: "\"{$a->title}\" will be permanently deleted. This cannot be undone.",
            ))
            ->all();
    }

    private function countries(): array
    {
        return Country::onlyTrashed()
            ->latest('deleted_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn (Country $country) => $this->row(
                type: 'countries',
                id: $country->id,
                name: $country->name,
                subtitle: 'Country',
                avatar: null,
                deletedAt: $country->deleted_at,
                restoreRoute: route('admin.trash.countries.restore', $country->id),
                forceDeleteRoute: route('admin.trash.countries.destroy', $country->id),
                forceDeleteMessage: "\"{$country->name}\" and every state and city under it will be "
                    . 'permanently deleted. This cannot be undone.',
            ))
            ->all();
    }

    private function states(): array
    {
        return State::onlyTrashed()
            ->with(['country' => fn ($q) => $q->withTrashed()->select('id', 'name')])
            ->latest('deleted_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn (State $state) => $this->row(
                type: 'states',
                id: $state->id,
                name: $state->name,
                subtitle: $state->country?->name ?: '—',
                avatar: null,
                deletedAt: $state->deleted_at,
                restoreRoute: route('admin.trash.states.restore', $state->id),
                forceDeleteRoute: route('admin.trash.states.destroy', $state->id),
                forceDeleteMessage: "\"{$state->name}\" and every city under it will be permanently "
                    . 'deleted. This cannot be undone.',
            ))
            ->all();
    }

    private function cities(): array
    {
        return City::onlyTrashed()
            ->with(['state' => fn ($q) => $q->withTrashed()->select('id', 'name')])
            ->latest('deleted_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn (City $city) => $this->row(
                type: 'cities',
                id: $city->id,
                name: $city->name,
                subtitle: $city->state?->name ?: '—',
                avatar: null,
                deletedAt: $city->deleted_at,
                restoreRoute: route('admin.trash.cities.restore', $city->id),
                forceDeleteRoute: route('admin.trash.cities.destroy', $city->id),
                forceDeleteMessage: "\"{$city->name}\" will be permanently deleted. This cannot be undone.",
            ))
            ->all();
    }

    private function roles(): array
    {
        return Role::onlyTrashed()
            ->latest('deleted_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get()
            ->map(fn (Role $role) => $this->row(
                type: 'roles',
                id: $role->id,
                name: $role->name,
                subtitle: 'Role',
                avatar: null,
                deletedAt: $role->deleted_at,
                restoreRoute: route('admin.trash.roles.restore', $role->id),
                forceDeleteRoute: route('admin.trash.roles.destroy', $role->id),
                forceDeleteMessage: "The \"{$role->name}\" role will be permanently deleted. This cannot be undone.",
            ))
            ->all();
    }

    /** One normalised row shape every type maps to — see the view, which knows only this shape. */
    private function row(
        string $type,
        int $id,
        string $name,
        string $subtitle,
        ?string $avatar,
        $deletedAt,
        string $restoreRoute,
        string $forceDeleteRoute,
        string $forceDeleteMessage,
    ): array {
        [$icon, $typeLabel] = self::TYPES[$type];

        return [
            'type' => $type,
            'type_label' => $typeLabel,
            'icon' => $icon,
            'id' => $id,
            'name' => $name,
            'subtitle' => $subtitle,
            'avatar' => $avatar,
            'deleted_at' => $deletedAt,
            'restore_route' => $restoreRoute,
            'force_delete_route' => $forceDeleteRoute,
            'force_delete_message' => $forceDeleteMessage,
        ];
    }
}
