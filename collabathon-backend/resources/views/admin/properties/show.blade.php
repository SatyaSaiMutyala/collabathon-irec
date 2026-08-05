@php
    /**
     * The whole project sheet, read-only, in the same nine groups the intake form uses.
     *
     * Empty fields still render as "—" rather than being hidden: on a 69-field sheet, an
     * admin needs to see what is *missing* as much as what is filled in, and a section that
     * silently collapses reads as "there is nothing more to enter".
     */
    $detail = $property->detail;
    $media = $property->media->groupBy('kind');

    $statusTone = ['active' => 'success', 'draft' => 'warning', 'archived' => 'neutral'];

    $money = fn ($v) => $v === null ? null : $property->currency . ' ' . number_format((float) $v);
    $trim = fn ($v) => $v === null ? null : rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $date = fn ($d) => $d?->format('d M Y');
    $list = fn (?array $v) => $v ? implode("\n", $v) : null;

    $deletePayload = \Illuminate\Support\Js::from([
        'title' => 'Delete this project?',
        'message' => "\"{$property->name}\" will be removed from every listing and from channel partner "
            . 'view. Leads already raised against it are kept.',
        'confirmLabel' => 'Delete project',
        'tone' => 'danger',
    ]);

    // label => value, per group. Rendered by the <dl> helper markup below.
    $groups = [
        'Project basics' => [
            'Project name' => $property->name,
            'Developer / builder' => $property->developer?->company_name,
            'Project type' => $property->project_type,
            'Project status' => $property->project_status,
            'Possession date' => $date($property->possession_date),
            'Heading' => $property->tagline,
            // The registered-on / valid-till dates are no longer collected on the form, so
            // they are not shown either — a read-only row for a field nothing can edit
            // reads as a broken control. Both columns still hold their existing values.
            'RERA registration number' => $property->rera_number,
        ],
        'Location' => [
            'State / Emirate' => $property->state,
            'City' => $property->city,
            'Locality / area' => $property->locality,
            'Full address' => $property->full_address,
            'Landmark' => $property->landmark,
            'Pincode' => $property->pincode,
            'Zone' => $property->zone,
            'Coordinates' => $property->latitude && $property->longitude
                ? $property->latitude . ', ' . $property->longitude
                : null,
            'Google Maps' => $property->maps_link,
            'Connectivity highlights' => $list($detail?->connectivity_highlights),
            'Nearby infrastructure' => $list($detail?->nearby_infrastructure),
        ],
        'Configuration & pricing' => [
            // A band only when there is an upper end on record; intake collects the entry
            // price alone now, so most records read "From <price>".
            'Price' => match (true) {
                $property->price_min === null => null,
                $property->price_max === null => 'From ' . $money($property->price_min),
                default => $money($property->price_min) . ' – ' . number_format((float) $property->price_max),
            },
            'Project extent metric' => $property->extent_metric,
            'Total units' => $property->total_units,
            'Towers / blocks' => $property->towers,
            'No. of floors' => $property->floors_per_tower,
        ],
        'Specifications' => [
            'Land parcel' => $property->land_parcel_acres ? $trim($property->land_parcel_acres) . ' acres' : null,
            'Total project area' => $property->total_project_area_sqft
                ? number_format($property->total_project_area_sqft) . ' sq.ft.' : null,
            'Open / green space' => $property->open_space_percent ? $property->open_space_percent . '%' : null,
            'Green certification' => $property->green_certification,
            'Vastu compliant' => $property->vastu_compliant ? 'Yes' : 'No',
        ],
        // The Timeline & legal group went with its intake step. Possession date survived
        // it — that field is collected in step 1 — so it moves up to the basics it now
        // belongs with rather than heading a group of its own.
        'Commercial terms' => [
            'Payment plans' => $list($detail?->payment_plan_options),
            'Booking amount' => $money($detail?->booking_amount),
            'CP commission' => $detail?->cp_commission_percent !== null
                ? $trim($detail->cp_commission_percent) . '%' : null,
            'Special CP incentives' => $detail?->special_incentives,
            'Cashback / discounts' => $detail?->cashback_schemes,
            'Registration & stamp duty' => $detail?->registration_stamp_duty,
            'Maintenance charges' => $detail?->maintenance_charges,
            'Floor rise' => $detail?->floor_rise,
            'PLC charges' => $detail?->plc_charges,
            'Other charges' => $list($detail?->other_charges),
            'Payment schedule' => $detail?->payment_schedule,
        ],
        'Contact & sales' => [
            'Sales office' => $detail?->sales_office_address,
            'Site visit timings' => $detail?->site_visit_timings,
            'Sales contact' => trim(($detail?->sales_contact_name ?? '') . ' ' . ($detail?->sales_contact_number ?? '')) ?: null,
            'Booking process' => $detail?->booking_process,
        ],
        // Compliance & trust held only awards, which is no longer collected either.
    ];

    // Attachment kinds, in the order the sheet lists them.
    $attachmentKinds = [
        'site_layout' => 'Site layout plan',
        'master_plan' => 'Master plan',
        'brochure' => 'Brochure',
        'price_list' => 'Price list',
        'payment_schedule' => 'Payment schedule',
        'rera_certificate' => 'RERA QR / certificate',
        'unit_plan' => 'Unit plan / layout',
    ];
@endphp

<x-layouts.admin active="properties" :title="$property->name" section="Manage">

    <a href="{{ route('admin.properties') }}"
       class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2 hover:text-ink transition-colors mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" />
        Back to projects
    </a>

    {{-- ============================== Header ============================== --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div class="flex items-start gap-3.5 min-w-0">
            @if($property->cover_image_path || $property->logo_path)
                <img src="{{ asset('storage/' . ($property->cover_image_path ?: $property->logo_path)) }}"
                     alt="" class="w-14 h-14 rounded-xl object-cover border border-line-soft shrink-0">
            @else
                <x-avatar :name="$property->name" size="lg" class="w-14 h-14 shrink-0" />
            @endif

            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-[19px] sm:text-[21px] font-semibold text-ink tracking-[-0.02em] leading-tight">
                        {{ $property->name }}
                    </h1>
                    <x-badge :tone="$statusTone[$property->listing_status] ?? 'neutral'" size="sm" dot>
                        {{ ucfirst($property->listing_status) }}
                    </x-badge>
                    <x-badge tone="neutral" size="sm">{{ $property->project_status }}</x-badge>

                    {{-- The developer's half of the gate, beside the admin's half. --}}
                    @php
                        $devTone = ['accepted' => 'success', 'pending' => 'warning', 'declined' => 'danger'];
                        $devLabel = [
                            'accepted' => 'Developer accepted',
                            'pending' => 'Awaiting developer',
                            'declined' => 'Developer declined',
                        ];
                    @endphp
                    <x-badge :tone="$devTone[$property->developer_status] ?? 'neutral'" size="sm" dot>
                        {{ $devLabel[$property->developer_status] ?? ucfirst($property->developer_status) }}
                    </x-badge>
                </div>

                {{-- Visibility is the two keys together, so it is stated once, plainly,
                     rather than left for the reader to infer from two badges. --}}
                @if($property->listing_status === 'active' && $property->developer_status === 'accepted')
                    <p class="flex items-center gap-1.5 text-[12px] text-success mt-2">
                        <x-icon name="check" class="w-3.5 h-3.5" />
                        Live — channel partners can see this project
                        @if($property->developer_responded_at)
                            <span class="text-ink-3">· accepted {{ $property->developer_responded_at->diffForHumans() }}</span>
                        @endif
                    </p>
                @elseif($property->developer_status === 'pending')
                    <p class="flex items-center gap-1.5 text-[12px] text-warning mt-2">
                        <x-icon name="clock" class="w-3.5 h-3.5" />
                        Hidden from partners — waiting for
                        {{ $property->developer?->company_name ?? 'the developer' }} to accept it
                    </p>
                @elseif($property->developer_status === 'declined')
                    <p class="flex items-start gap-1.5 text-[12px] text-danger mt-2">
                        <x-icon name="x" class="w-3.5 h-3.5 shrink-0 mt-0.5" />
                        <span>
                            Declined by the developer.
                            @if($property->developer_decline_reason)
                                <span class="text-ink-2">Reason: {{ $property->developer_decline_reason }}</span>
                            @endif
                        </span>
                    </p>
                @else
                    <p class="flex items-center gap-1.5 text-[12px] text-ink-3 mt-2">
                        <x-icon name="clock" class="w-3.5 h-3.5" />
                        Accepted by the developer, but not published — set the listing Active to go live.
                    </p>
                @endif
                <p class="text-[13px] text-ink-2 mt-1">
                    {{ $property->developer?->company_name ?? 'No developer' }}
                    @if($property->locality || $property->city)
                        · {{ collect([$property->locality, $property->city])->filter()->implode(', ') }}
                    @endif
                    · Added {{ $property->created_at->format('d M Y') }}
                </p>
                @if($property->tagline)
                    <p class="text-[12.5px] text-ink-3 mt-1 italic">{{ $property->tagline }}</p>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <x-button variant="gold" icon="cog" tag="a" href="{{ route('admin.properties.edit', $property) }}">
                Edit
            </x-button>

            <x-dropdown>
                <x-slot:trigger>
                    <button type="button" aria-label="More actions"
                            class="h-9 w-9 flex items-center justify-center rounded-lg bg-panel border border-line
                                   text-ink-2 hover:text-ink hover:bg-canvas transition-colors">
                        <x-icon name="dots" class="w-4 h-4" />
                    </button>
                </x-slot:trigger>

                <x-dropdown-item icon="users" tag="a" href="{{ route('admin.leads', ['search' => $property->name]) }}">
                    View leads
                </x-dropdown-item>

                @foreach(['draft' => 'Move to draft', 'active' => 'Publish listing', 'archived' => 'Archive listing'] as $value => $label)
                    @if($property->listing_status !== $value)
                        <form method="POST" action="{{ route('admin.properties.update', $property) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="listing_status" value="{{ $value }}">
                            <x-dropdown-item :icon="$value === 'active' ? 'check' : ($value === 'archived' ? 'x' : 'list')"
                                             tag="button" type="submit">{{ $label }}</x-dropdown-item>
                        </form>
                    @endif
                @endforeach

                <div class="my-1 border-t border-line-soft"></div>

                <form method="POST" action="{{ route('admin.properties.destroy', $property) }}"
                      x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $deletePayload }}, form: $el }); close()">
                    @csrf @method('DELETE')
                    <x-dropdown-item icon="x" tone="danger" tag="button" type="submit">
                        Delete project
                    </x-dropdown-item>
                </form>
            </x-dropdown>
        </div>
    </div>

    {{-- ============================== Activity ============================== --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-5">
        <x-stat-card icon="eye" label="Views" :value="number_format($stats['views'])" />
        <x-stat-card icon="sparkles" label="Interests" :value="number_format($stats['interests'])" />
        <x-stat-card icon="users" label="Leads" :value="$stats['leads']" />
        <x-stat-card icon="list" label="Unit types" :value="$stats['units']" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">

        {{-- ============================== Left: the sheet ============================== --}}
        <div class="xl:col-span-2 space-y-4">
            @if($property->description)
                <x-panel title="Description" padded>
                    <p class="text-[13px] text-ink-2 leading-relaxed whitespace-pre-line">{{ $property->description }}</p>
                </x-panel>
            @endif

            @foreach($groups as $title => $fields)
                <x-panel :title="$title" flush>
                    <dl class="divide-y divide-line-soft">
                        @foreach($fields as $label => $value)
                            <div class="px-5 py-3 flex items-start gap-4">
                                <dt class="text-[12.5px] text-ink-3 w-[168px] shrink-0">{{ $label }}</dt>
                                <dd class="text-[13px] text-ink min-w-0 break-words whitespace-pre-line flex-1">
                                    @if(filled($value) && \Illuminate\Support\Str::startsWith((string) $value, ['http://', 'https://']))
                                        <a href="{{ $value }}" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-1 text-primary hover:underline">
                                            Open <x-icon name="external" class="w-3.5 h-3.5" />
                                        </a>
                                    @else
                                        {{ filled($value) ? $value : '—' }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </x-panel>
            @endforeach

            {{-- Developer terms ----------------------------------------------- --}}
            @if($detail?->hasTerms())
                <x-panel :title="$detail->terms_title ?: 'Developer terms'"
                         :subtitle="$detail->terms_type === 'document' ? 'Document · shown to the developer and to channel partners' : 'Typed content · shown to the developer and to channel partners'"
                         :padded="$detail->terms_type === 'text'"
                         :flush="$detail->terms_type === 'document'">
                    @if($detail->terms_type === 'document')
                        <div class="px-5 py-4 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <x-icon name="download" class="w-4 h-4 text-ink-3 shrink-0" />
                                <p class="text-[12.5px] text-ink truncate">{{ basename($detail->terms_document_path) }}</p>
                            </div>
                            <x-button variant="subtle" size="sm" tag="a" target="_blank"
                                      href="{{ asset('storage/' . $detail->terms_document_path) }}">
                                Open
                            </x-button>
                        </div>
                    @else
                        {{-- Sanitised on write by App\Support\RichText, so the stored markup
                             is already the allowlist — see the note there on why this is not
                             re-cleaned per render. --}}
                        <div class="rich-text text-[13px] text-ink">{!! $detail->terms_content !!}</div>
                    @endif
                </x-panel>
            @endif

            {{-- Unit types ---------------------------------------------------- --}}
            <x-panel title="Unit types" :subtitle="$property->unitTypes->count() . ' configured'" flush>
                @if($property->unitTypes->isEmpty())
                    <div class="px-5 py-6 text-center text-[12.5px] text-ink-3">
                        No unit types added yet.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="border-b border-line-soft bg-canvas">
                                    @foreach(['Unit', 'Carpet', 'Built-up', 'Super built-up', 'Price range', 'Units', 'Floor plan'] as $head)
                                        <th class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3 whitespace-nowrap">
                                            {{ $head }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line-soft">
                                @foreach($property->unitTypes as $unit)
                                    <tr>
                                        <td class="px-4 py-3 text-[13px] font-medium text-ink whitespace-nowrap">{{ $unit->label }}</td>
                                        {{-- sq.m. alongside sq.ft. — the sheet asks for both, and one is
                                             derived from the other, so it is computed rather than stored. --}}
                                        @foreach(['carpet_area_sqft', 'built_up_area_sqft', 'super_built_up_area_sqft'] as $area)
                                            <td class="px-4 py-3 text-[12.5px] text-ink-2 nums whitespace-nowrap">
                                                @if($unit->{$area})
                                                    {{ number_format($unit->{$area}) }} sq.ft.
                                                    <span class="text-ink-3">({{ number_format($unit->{$area} * 0.092903, 1) }} m²)</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="px-4 py-3 text-[12.5px] text-ink-2 nums whitespace-nowrap">
                                            {{ $unit->price_min ? number_format($unit->price_min) . ' – ' . number_format($unit->price_max) : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-[12.5px] text-ink-2 nums">{{ $unit->units_count ?? '—' }}</td>
                                        <td class="px-4 py-3">
                                            @if($unit->floor_plan_path)
                                                <a href="{{ asset('storage/' . $unit->floor_plan_path) }}"
                                                   target="_blank" rel="noopener"
                                                   class="inline-flex items-center gap-1 text-[12.5px] text-primary hover:underline">
                                                    View <x-icon name="external" class="w-3.5 h-3.5" />
                                                </a>
                                            @else
                                                <span class="text-[12.5px] text-ink-3">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-panel>
        </div>

        {{-- ============================== Right: media & amenities ============================== --}}
        <div class="space-y-4">
            <x-panel title="Gallery" :subtitle="($media->get('image')?->count() ?? 0) . ' images'" padded>
                @if($media->get('image')?->isNotEmpty())
                    <div class="grid grid-cols-3 gap-2">
                        @foreach($media['image'] as $image)
                            @php $imageUrl = $image->url ?: asset('storage/' . $image->path); @endphp
                            <a href="{{ $imageUrl }}" target="_blank" rel="noopener"
                               class="block rounded-lg overflow-hidden border border-line hover:opacity-90 transition-opacity">
                                <img src="{{ $imageUrl }}" alt=""
                                     class="w-full aspect-square object-cover">
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-[12.5px] text-ink-3">No images uploaded yet.</p>
                @endif
            </x-panel>

            <x-panel title="Amenities" padded>
                @if($detail?->amenities)
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($detail->amenities as $amenity)
                            <x-badge tone="neutral" size="sm">{{ $amenity }}</x-badge>
                        @endforeach
                    </div>
                @else
                    <p class="text-[12.5px] text-ink-3">None recorded.</p>
                @endif
            </x-panel>

            <x-panel title="Attachments" flush>
                <dl class="divide-y divide-line-soft">
                    @foreach($attachmentKinds as $kind => $label)
                        <div class="px-5 py-3 flex items-center justify-between gap-3">
                            <dt class="text-[12.5px] text-ink-3">{{ $label }}</dt>
                            <dd class="text-[12.5px] shrink-0">
                                @php $items = $media->get($kind); @endphp
                                @if($items?->isNotEmpty())
                                    <span class="flex items-center gap-2">
                                        @foreach($items as $i => $item)
                                            <a href="{{ asset('storage/' . $item->path) }}"
                                               target="_blank" rel="noopener"
                                               class="inline-flex items-center gap-1 text-primary hover:underline">
                                                {{ $items->count() > 1 ? '#' . ($i + 1) : 'View' }}
                                                <x-icon name="external" class="w-3 h-3" />
                                            </a>
                                        @endforeach
                                    </span>
                                @else
                                    <span class="text-ink-3">—</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach

                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <dt class="text-[12.5px] text-ink-3">Legal due diligence</dt>
                        <dd class="text-[12.5px] shrink-0">
                            @if($detail?->legal_due_diligence_path)
                                <a href="{{ asset('storage/' . $detail->legal_due_diligence_path) }}"
                                   target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1 text-primary hover:underline">
                                    View <x-icon name="external" class="w-3 h-3" />
                                </a>
                            @else
                                <span class="text-ink-3">—</span>
                            @endif
                        </dd>
                    </div>

                    @foreach(['video' => 'Video / walkthrough', 'virtual_tour' => 'Virtual tour'] as $kind => $label)
                        <div class="px-5 py-3 flex items-center justify-between gap-3">
                            <dt class="text-[12.5px] text-ink-3">{{ $label }}</dt>
                            <dd class="text-[12.5px] shrink-0">
                                @if($url = $media->get($kind)?->first()?->url)
                                    <a href="{{ $url }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1 text-primary hover:underline">
                                        Open <x-icon name="external" class="w-3 h-3" />
                                    </a>
                                @else
                                    <span class="text-ink-3">—</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-panel>

            <x-panel title="Record" flush>
                <dl class="divide-y divide-line-soft">
                    <div class="px-5 py-3 flex items-center justify-between gap-4">
                        <dt class="text-[12.5px] text-ink-3">Slug</dt>
                        <dd class="text-[12px] text-ink-2 font-mono break-all">{{ $property->slug }}</dd>
                    </div>
                    <div class="px-5 py-3 flex items-center justify-between gap-4">
                        <dt class="text-[12.5px] text-ink-3">Last updated</dt>
                        <dd class="text-[12.5px] text-ink-2 nums">{{ $property->updated_at->format('d M Y') }}</dd>
                    </div>
                </dl>
            </x-panel>
        </div>
    </div>
</x-layouts.admin>
