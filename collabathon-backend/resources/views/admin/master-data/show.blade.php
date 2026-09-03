@php
    $profile = $record['developer_profile'] ?? [];
    $project = $record['project_details'] ?? [];
    $assets = $record['visual_assets'] ?? [];
    $documents = $record['documents'] ?? [];
    $sync = $record['sync_meta'] ?? [];
    $social = $profile['social_links'] ?? [];
    $commercials = $project['channel_partner_commercials'] ?? [];
    $geo = $project['geo_coordinates'] ?? [];

    $socialCell = collect($social)->filter()->map(fn ($handle, $key) => ucfirst(str_replace('_x', '', $key)) . ': ' . $handle)->implode(' · ');
@endphp

<x-layouts.admin active="master_data" title="{{ $profile['company_name'] ?? 'Registration' }}" section="Manage">

    <a href="{{ route('admin.master-data') }}"
       class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2 hover:text-ink transition-colors mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" />
        Back to Master Data
    </a>

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div class="flex items-start gap-3.5 flex-1 min-w-[260px]">
            @if($profile['builder_logo_url'] ?? null)
                <img src="{{ $profile['builder_logo_url'] }}" alt="{{ $profile['company_name'] ?? '' }}"
                     class="w-14 h-14 rounded-xl object-cover border border-line-soft shrink-0">
            @else
                <x-avatar :name="$profile['company_name'] ?? '—'" size="lg" class="w-14 h-14 shrink-0" />
            @endif

            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-[19px] sm:text-[21px] font-semibold text-ink tracking-[-0.02em] leading-tight">
                        {{ $profile['company_name'] ?? '—' }}
                    </h1>
                    <x-badge tone="neutral" size="sm">{{ ucfirst($sync['status'] ?? 'unknown') }}</x-badge>
                </div>
                <p class="text-[13px] text-ink-2 mt-1">
                    {{ $record['reference_code'] ?? '' }}
                    @if($project['project_name'] ?? null) · {{ $project['project_name'] }} @endif
                    @if($sync['created_at'] ?? null) · Registered {{ \Illuminate\Support\Carbon::parse($sync['created_at'])->format('d M Y') }} @endif
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            @if($developer)
                <x-button variant="outline" icon="check" tag="a" href="{{ route('admin.developers.show', $developer) }}">
                    Already converted — view developer
                </x-button>
            @else
                <form method="POST" action="{{ route('admin.master-data.convert', $record['registration_id']) }}">
                    @csrf
                    <x-button variant="primary" tag="button" type="submit" icon="check">
                        Convert developer
                    </x-button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">
        <div class="xl:col-span-2 space-y-4">

            {{-- ---------------------------- Contact & business ---------------------------- --}}
            <x-panel title="Developer profile" flush>
                <x-detail-grid :fields="[
                    ['label' => 'Key contact', 'value' => $profile['key_contact_person'] ?? null],
                    ['label' => 'Designation', 'value' => $profile['designation'] ?? null],
                    ['label' => 'Email', 'value' => $profile['email'] ?? null],
                    ['label' => 'Mobile', 'value' => $profile['mobile'] ?? null],
                    ['label' => 'Website', 'value' => $profile['website'] ?? null],
                    ['label' => 'Social media', 'value' => $socialCell ?: null],
                    ['label' => 'Registered address', 'value' => $profile['registered_address'] ?? null, 'wide' => true],
                    ['label' => 'About', 'value' => $profile['about_company'] ?? ($profile['brief_description'] ?? null), 'wide' => true],
                ]" />
            </x-panel>

            <x-panel title="Coverage" flush>
                <x-detail-grid :fields="[
                    ['label' => 'City', 'value' => $profile['city'] ?? null],
                    ['label' => 'State', 'value' => $profile['state'] ?? null],
                    ['label' => 'Country', 'value' => $profile['country'] ?? null],
                    ['label' => 'Pincode', 'value' => $profile['pincode'] ?? null],
                ]" />
            </x-panel>

            {{-- ---------------------------- Project ---------------------------- --}}
            <x-panel title="Project" flush>
                <x-detail-grid :fields="[
                    ['label' => 'Project name', 'value' => $project['project_name'] ?? null],
                    ['label' => 'Type', 'value' => $project['project_type'] ?? null],
                    ['label' => 'Status', 'value' => $project['project_status'] ?? null],
                    ['label' => 'Possession', 'value' => $project['possession_date'] ?? null],
                    ['label' => 'Price starts from', 'value' => $project['price_starts_from_inr'] ?? null],
                    ['label' => 'RERA number', 'value' => $project['rera_number'] ?? null],
                    ['label' => 'Tagline', 'value' => $project['title_tagline'] ?? null, 'wide' => true],
                    ['label' => 'Description', 'value' => $project['project_description'] ?? null, 'wide' => true],
                    ['label' => 'Address', 'value' => $project['full_address'] ?? null, 'wide' => true],
                    ['label' => 'Locality / landmark', 'value' => trim(($project['locality_area'] ?? '') . ' · ' . ($project['landmark'] ?? ''), ' ·') ?: null],
                    ['label' => 'Zone', 'value' => $project['zone'] ?? null],
                    ['label' => 'Connectivity', 'value' => $project['connectivity_highlights'] ?? null, 'wide' => true],
                    ['label' => 'Nearby infrastructure', 'value' => $project['nearby_social_infra'] ?? null, 'wide' => true],
                    ['label' => 'Land parcel', 'value' => ($project['land_parcel_acres'] ?? null) ? $project['land_parcel_acres'] . ' ' . ($project['extent_metric'] ?? '') : null],
                    ['label' => 'Total area', 'value' => $project['total_project_area_sqft'] ?? null],
                    ['label' => 'Total units', 'value' => $project['total_units'] ?? null],
                    ['label' => 'Blocks / towers', 'value' => $project['blocks_towers'] ?? null],
                    ['label' => 'Floors', 'value' => $project['number_of_floors'] ?? null],
                    ['label' => 'Maps link', 'value' => $geo['maps_link'] ?? null],
                ]" />
            </x-panel>

            @if(! empty($project['dynamic_unit_configurations']))
                <x-panel title="Unit configurations" flush>
                    <div class="overflow-x-auto scrollbar-slim">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-line-soft">
                                    <x-th>Configuration</x-th>
                                    <x-th>Size (sqft)</x-th>
                                    <x-th>Units</x-th>
                                    <x-th align="right">Price</x-th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line-soft">
                                @foreach($project['dynamic_unit_configurations'] as $unit)
                                    <tr>
                                        <td class="px-4 py-3 text-[12.5px] text-ink">{{ $unit['bhk'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-[12.5px] text-ink-2 nums">{{ $unit['size'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-[12.5px] text-ink-2 nums">{{ $unit['units'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-[12.5px] text-ink-2 nums text-right">{{ $unit['price'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-panel>
            @endif

            @if(! empty($project['amenities_list']))
                <x-panel title="Amenities" :subtitle="count($project['amenities_list']) . ' listed'" flush>
                    <div class="px-4 py-4 flex flex-wrap gap-1.5">
                        @foreach($project['amenities_list'] as $amenity)
                            <x-badge tone="neutral" size="sm">{{ $amenity }}</x-badge>
                        @endforeach
                    </div>
                </x-panel>
            @endif

            <x-panel title="Channel partner commercials" flush>
                <x-detail-grid :fields="[
                    ['label' => 'CP commission', 'value' => $commercials['cp_commission'] ?? null],
                    ['label' => 'FOS commission', 'value' => $commercials['fos_commission'] ?? null],
                    ['label' => 'Sales contact', 'value' => $commercials['sales_contact_name'] ?? null],
                    ['label' => 'Sales contact number', 'value' => $commercials['sales_contact_number'] ?? null],
                    ['label' => 'Sales office', 'value' => $commercials['sales_office_address'] ?? null, 'wide' => true],
                    ['label' => 'Site visit timings', 'value' => $commercials['site_visit_timings'] ?? null, 'wide' => true],
                    ['label' => 'Booking checklist', 'value' => $commercials['booking_checklist'] ?? null, 'wide' => true],
                ]" />
            </x-panel>

            @if(! empty($assets['gallery_images']))
                <x-panel title="Gallery" flush>
                    <div class="p-4 grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($assets['gallery_images'] as $image)
                            <a href="{{ $image['image_url'] ?? '#' }}" target="_blank" rel="noopener" class="block group">
                                <img src="{{ $image['image_url'] ?? '' }}" alt="{{ $image['caption'] ?? '' }}"
                                     class="w-full aspect-video object-cover rounded-lg border border-line-soft group-hover:opacity-90 transition-opacity">
                                @if($image['caption'] ?? null)
                                    <p class="text-[11.5px] text-ink-3 mt-1 truncate">{{ $image['caption'] }}</p>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </x-panel>
            @endif
        </div>

        <div class="space-y-4">
            @if($assets['hero_image_url'] ?? null)
                <x-panel title="Hero image" flush>
                    <a href="{{ $assets['hero_image_url'] }}" target="_blank" rel="noopener">
                        <img src="{{ $assets['hero_image_url'] }}" alt="" class="w-full aspect-video object-cover">
                    </a>
                </x-panel>
            @endif

            @if($assets['master_layout_url'] ?? null)
                <x-panel title="Master layout" flush>
                    <a href="{{ $assets['master_layout_url'] }}" target="_blank" rel="noopener">
                        <img src="{{ $assets['master_layout_url'] }}" alt="" class="w-full object-cover">
                    </a>
                </x-panel>
            @endif

            @if(! empty($project['floor_plans_each_unit']))
                <x-panel title="Floor plans" flush>
                    <div class="divide-y divide-line-soft">
                        @foreach($project['floor_plans_each_unit'] as $plan)
                            <a href="{{ $plan['image_url'] ?? '#' }}" target="_blank" rel="noopener"
                               class="flex items-center gap-3 px-4 py-3 hover:bg-canvas transition-colors">
                                <img src="{{ $plan['image_url'] ?? '' }}" alt=""
                                     class="w-14 h-14 rounded-lg object-cover border border-line-soft shrink-0">
                                <div class="min-w-0">
                                    <p class="text-[12.5px] font-medium text-ink truncate">{{ $plan['plan_type'] ?? $plan['title'] ?? 'Floor plan' }}</p>
                                    <p class="text-[11.5px] text-ink-3 truncate">{{ $plan['area'] ?? '' }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </x-panel>
            @endif

            @php $documentLinks = collect($documents)->filter(); @endphp
            @if($documentLinks->isNotEmpty())
                <x-panel title="Documents" flush>
                    <div class="divide-y divide-line-soft">
                        @foreach($documentLinks as $key => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener"
                               class="flex items-center gap-2.5 px-4 py-3 hover:bg-canvas transition-colors">
                                <x-icon name="file-text" class="w-4 h-4 text-ink-3 shrink-0" />
                                <span class="text-[12.5px] text-ink truncate">{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                                <x-icon name="external" class="w-3.5 h-3.5 text-ink-3 ml-auto shrink-0" />
                            </a>
                        @endforeach
                    </div>
                </x-panel>
            @endif
        </div>
    </div>
</x-layouts.admin>
