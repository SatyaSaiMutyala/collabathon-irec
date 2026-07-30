@php
    use App\Models\Lead;

    /**
     * One broker's interaction with one project, plus the controls to move it.
     *
     * Every stage is offered regardless of where the lead currently sits — accepting by
     * mistake has to be undoable, and a declined lead has to be reopenable.
     */
    $broker = $lead->broker;
    $profile = $broker?->brokerProfile;
    $property = $lead->property;

    $toneMap = [
        Lead::STATUS_VIEWED => 'neutral',
        Lead::STATUS_INTERESTED => 'warning',
        Lead::STATUS_ACCEPTED => 'success',
        Lead::STATUS_DECLINED => 'danger',
    ];

    $stages = [
        Lead::STATUS_VIEWED => ['label' => 'Viewed', 'icon' => 'eye', 'hint' => 'Contact stays locked'],
        Lead::STATUS_INTERESTED => ['label' => 'Interested', 'icon' => 'sparkles', 'hint' => 'Unlocks contact'],
        Lead::STATUS_ACCEPTED => ['label' => 'Accepted', 'icon' => 'check', 'hint' => 'Developer took the lead'],
        Lead::STATUS_DECLINED => ['label' => 'Declined', 'icon' => 'x', 'hint' => 'Developer passed'],
    ];

    // The three timestamps, in flow order. Nulls are shown so a gap is visible.
    $timeline = [
        'Recorded' => $lead->created_at,
        'Viewed' => $lead->viewed_at,
        'Marked interested' => $lead->interested_at,
        'Developer responded' => $lead->responded_at,
    ];
@endphp

<x-layouts.admin active="leads" :title="($broker?->name ?? 'Lead') . ' · ' . ($property?->name ?? '')" section="Manage">

    <a href="{{ route('admin.leads') }}"
       class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2 hover:text-ink transition-colors mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" />
        Back to approvals
    </a>

    {{-- ============================== Header ============================== --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div class="flex items-start gap-3.5 min-w-0">
            <x-avatar :name="$broker?->name ?? '—'" size="lg" class="w-14 h-14 shrink-0" />

            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-[19px] sm:text-[21px] font-semibold text-ink tracking-[-0.02em] leading-tight">
                        {{ $broker?->name ?? 'Deleted broker' }}
                    </h1>
                    <x-badge :tone="$toneMap[$lead->status] ?? 'neutral'" size="sm" dot>
                        {{ ucfirst($lead->status) }}
                    </x-badge>
                    <x-badge :tone="$lead->contact_unlocked ? 'success' : 'neutral'" size="sm">
                        <x-icon :name="$lead->contact_unlocked ? 'check' : 'lock'" class="w-3 h-3" />
                        {{ $lead->contact_unlocked ? 'Contact unlocked' : 'Contact locked' }}
                    </x-badge>
                </div>
                <p class="text-[13px] text-ink-2 mt-1">
                    {{ $profile?->company_name ?: 'Independent broker' }}
                    @if($property) · interested in {{ $property->name }}@endif
                    · {{ $lead->created_at->format('d M Y') }}
                </p>
            </div>
        </div>

        @if($property)
            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <x-button variant="outline" icon="building" tag="a" href="{{ route('admin.properties.show', $property) }}">
                    View project
                </x-button>
                @if($broker)
                    <x-button variant="subtle" icon="user-check" tag="a"
                              href="{{ route('admin.approvals.show', $broker) }}">
                        Broker file
                    </x-button>
                @endif
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">

        {{-- ============================== Left ============================== --}}
        <div class="xl:col-span-2 space-y-4">

            {{-- Stage control ------------------------------------------------ --}}
            <x-panel title="Stage" subtitle="Change it in either direction — nothing here is one-way" flush>
                <form method="POST" action="{{ route('admin.leads.update', $lead) }}" class="p-4 space-y-3">
                    @csrf @method('PATCH')

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        @foreach($stages as $value => $stage)
                            @php $isCurrent = $lead->status === $value; @endphp
                            <label @class([
                                'relative flex flex-col gap-1 rounded-lg border p-3 transition-colors',
                                'cursor-pointer',
                                'border-primary bg-primary-soft' => $isCurrent,
                                'border-line bg-panel hover:border-ink-3 hover:bg-canvas' => ! $isCurrent,
                            ])>
                                <input type="radio" name="status" value="{{ $value }}"
                                       @checked(old('status', $lead->status) === $value)
                                       class="peer sr-only">

                                <span class="flex items-center gap-1.5">
                                    <x-icon :name="$stage['icon']"
                                            :class="'w-4 h-4 shrink-0 ' . ($isCurrent ? 'text-primary-dark' : 'text-ink-3')" />
                                    <span class="text-[12.5px] font-medium text-ink">{{ $stage['label'] }}</span>
                                </span>
                                <span class="text-[11px] text-ink-3 leading-snug">{{ $stage['hint'] }}</span>

                                {{-- Ring is driven by :checked so the selection tracks the radio
                                     before the form is submitted, not just the saved status. --}}
                                <span class="pointer-events-none absolute inset-0 rounded-lg ring-2 ring-primary opacity-0
                                             peer-checked:opacity-100 transition-opacity"></span>
                            </label>
                        @endforeach
                    </div>

                    <x-field label="Developer note" name="developer_note" type="textarea" rows="2"
                             :value="$lead->developer_note"
                             placeholder="Why the developer accepted or passed — visible to the developer."
                             hint="Leave unchanged to keep the existing note." />

                    <div class="flex items-center justify-between gap-3 pt-1">
                        <p class="text-[11.5px] text-ink-3">
                            Last changed {{ $lead->updated_at->diffForHumans() }}
                        </p>
                        <x-button variant="gold" size="sm" tag="button" type="submit" icon="check">
                            Save stage
                        </x-button>
                    </div>
                </form>
            </x-panel>

            {{-- Broker ------------------------------------------------------- --}}
            <x-panel title="Broker" flush>
                <dl class="divide-y divide-line-soft">
                    @foreach([
                        'Name' => $broker?->name,
                        'Company' => $profile?->company_name,
                        'RERA number' => $profile?->rera_number,
                        'City' => $profile?->city,
                        'State' => $profile?->state,
                        'Years of experience' => $profile?->years_of_experience,
                        'Account status' => $broker ? ucfirst($broker->status) : null,
                    ] as $label => $value)
                        <div class="px-5 py-3 flex items-start gap-4">
                            <dt class="text-[12.5px] text-ink-3 w-[168px] shrink-0">{{ $label }}</dt>
                            <dd class="text-[13px] text-ink min-w-0 break-words">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach

                    {{-- Contact is shown to admin regardless: the platform rule governs what the
                         *developer* sees, and staff need it to chase a stalled lead. The banner
                         states which side of the gate the developer is on. --}}
                    <div class="px-5 py-3 flex items-start gap-4">
                        <dt class="text-[12.5px] text-ink-3 w-[168px] shrink-0">Email</dt>
                        <dd class="text-[13px] text-ink font-mono break-all">{{ $broker?->email ?? '—' }}</dd>
                    </div>
                    <div class="px-5 py-3 flex items-start gap-4">
                        <dt class="text-[12.5px] text-ink-3 w-[168px] shrink-0">Mobile</dt>
                        <dd class="text-[13px] text-ink nums">{{ $broker?->mobile ?: '—' }}</dd>
                    </div>
                </dl>

                <div @class([
                    'px-5 py-3 border-t border-line-soft flex items-start gap-2.5',
                    'bg-success-soft' => $lead->contact_unlocked,
                    'bg-canvas' => ! $lead->contact_unlocked,
                ])>
                    <x-icon :name="$lead->contact_unlocked ? 'check' : 'lock'"
                            :class="'w-4 h-4 shrink-0 mt-px ' . ($lead->contact_unlocked ? 'text-success' : 'text-ink-3')" />
                    <p class="text-[11.5px] text-ink-2 leading-relaxed">
                        @if($lead->contact_unlocked)
                            <span class="font-medium text-ink">{{ $lead->developer?->company_name ?? 'The developer' }}
                            can see these details</span> — unlocked when the broker marked the project interested.
                        @else
                            <span class="font-medium text-ink">Hidden from the developer.</span>
                            Contact unlocks only once the lead reaches “Interested”.
                        @endif
                    </p>
                </div>
            </x-panel>

            {{-- Project ------------------------------------------------------ --}}
            <x-panel title="Project" flush>
                <dl class="divide-y divide-line-soft">
                    @foreach([
                        'Project' => $property?->name,
                        'Developer' => $lead->developer?->company_name ?? $property?->developer?->company_name,
                        'Type' => $property?->project_type,
                        'Stage' => $property?->project_status,
                        'Location' => $property ? collect([$property->locality, $property->city])->filter()->implode(', ') : null,
                        'Price range' => $property?->price_min !== null
                            ? $property->currency . ' ' . number_format((float) $property->price_min)
                              . ' – ' . number_format((float) $property->price_max)
                            : null,
                        'Listing status' => $property ? ucfirst($property->listing_status) : null,
                    ] as $label => $value)
                        <div class="px-5 py-3 flex items-start gap-4">
                            <dt class="text-[12.5px] text-ink-3 w-[168px] shrink-0">{{ $label }}</dt>
                            <dd class="text-[13px] text-ink min-w-0 break-words">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-panel>
        </div>

        {{-- ============================== Right ============================== --}}
        <div class="space-y-4">
            <x-panel title="Timeline" flush>
                <dl class="divide-y divide-line-soft">
                    @foreach($timeline as $label => $at)
                        <div class="px-5 py-3 flex items-start justify-between gap-3">
                            <dt class="text-[12.5px] text-ink-3">{{ $label }}</dt>
                            <dd class="text-[12.5px] text-right shrink-0 {{ $at ? 'text-ink-2 nums' : 'text-ink-3' }}">
                                {{ $at ? $at->format('d M Y, H:i') : 'Not yet' }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-panel>

            <x-panel title="Developer note" padded>
                <p class="text-[13px] text-ink-2 leading-relaxed whitespace-pre-line">
                    {{ $lead->developer_note ?: 'No note recorded.' }}
                </p>
            </x-panel>

            <x-panel title="This broker's other activity" :subtitle="$brokerActivity->count() . ' recent'" flush>
                @forelse($brokerActivity as $other)
                    <a href="{{ route('admin.leads.show', $other) }}"
                       class="px-5 py-3 flex items-center justify-between gap-3 hover:bg-canvas transition-colors
                              {{ ! $loop->last ? 'border-b border-line-soft' : '' }}">
                        <span class="text-[12.5px] text-ink-2 truncate">{{ $other->property?->name ?? 'Deleted project' }}</span>
                        <x-badge :tone="$toneMap[$other->status] ?? 'neutral'" size="sm">
                            {{ ucfirst($other->status) }}
                        </x-badge>
                    </a>
                @empty
                    <p class="px-5 py-4 text-[12.5px] text-ink-3">No other activity from this broker.</p>
                @endforelse
            </x-panel>
        </div>
    </div>
</x-layouts.admin>
