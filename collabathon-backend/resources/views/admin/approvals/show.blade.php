@php
    $isPending = $broker->status === \App\Models\User::STATUS_PENDING;

    $statusTone = match ($broker->status) {
        \App\Models\User::STATUS_ACTIVE => 'success',
        \App\Models\User::STATUS_PENDING => 'warning',
        default => 'danger',
    };

    // Every uploaded document, in the order the mobile app collects them. Paths are
    // frequently null — a registration can reach the queue with none attached — so each
    // row renders either a link or a plain "Not provided".
    $documents = [
        ['label' => 'PAN card', 'number' => $profile?->pan_card, 'path' => $profile?->pan_card_path],
        ['label' => 'Aadhaar card', 'number' => $profile?->aadhaar_card, 'path' => $profile?->aadhaar_path],
        ['label' => 'RERA certificate', 'number' => $profile?->rera_number, 'path' => $profile?->rera_certificate_path],
        ['label' => 'GST certificate', 'number' => $profile?->gst_number, 'path' => $profile?->gst_path],
        ['label' => 'Cancelled cheque', 'number' => $profile?->cheque_details, 'path' => $profile?->cheque_path],
        ['label' => 'Signature', 'number' => null, 'path' => $profile?->signature_path],
    ];

    $attached = collect($documents)->filter(fn ($d) => filled($d['path']))->count();

    $rejectPayload = \Illuminate\Support\Js::from([
        'name' => $broker->name,
        'action' => route('admin.approvals.reject', $broker),
    ]);
@endphp

<x-layouts.admin active="approvals" :title="$broker->name" section="Manage">

    <a href="{{ route('admin.approvals') }}"
       class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2 hover:text-ink transition-colors mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" />
        Back to broker approvals
    </a>

    {{-- ============================== Header ============================== --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div class="flex items-start gap-3.5 min-w-0">
            @if($profile?->photo_path)
                <img src="{{ Storage::disk('public')->url($profile->photo_path) }}" alt=""
                     class="w-14 h-14 rounded-xl object-cover border border-line-soft shrink-0">
            @else
                <x-avatar :name="$broker->name" size="lg" class="w-14 h-14 shrink-0" />
            @endif

            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-[19px] sm:text-[21px] font-semibold text-ink tracking-[-0.02em] leading-tight">
                        {{ $broker->name }}
                    </h1>
                    <x-badge :tone="$statusTone" size="sm" dot>{{ ucfirst($broker->status) }}</x-badge>
                    @if($profile?->is_company)
                        <x-badge tone="neutral" size="sm">Company</x-badge>
                    @else
                        <x-badge tone="neutral" size="sm">Individual</x-badge>
                    @endif
                </div>
                <p class="text-[13px] text-ink-2 mt-1">
                    {{ $profile?->company_name ?: 'Independent broker' }}
                    @if($profile?->city) · {{ $profile->city }}@endif
                    · Submitted {{ ($profile?->submitted_at ?? $broker->created_at)->format('d M Y') }}
                </p>
            </div>
        </div>

        {{-- Actions are offered whatever the current state: a decision made by mistake has
             to be reversible. The wording changes so it is obvious an approved broker is
             being revoked, or a rejected one reinstated, rather than decided for the
             first time. --}}
        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            @if($broker->status !== \App\Models\User::STATUS_REJECTED)
                <x-button variant="outline" icon="x" tag="button" type="button"
                          x-on:click="$dispatch('open-reject')">
                    {{ $isPending ? 'Reject' : 'Revoke access' }}
                </x-button>
            @endif

            @if($broker->status !== \App\Models\User::STATUS_ACTIVE)
                <form method="POST" action="{{ route('admin.approvals.approve', $broker) }}">
                    @csrf
                    <x-button variant="primary" tag="button" type="submit" icon="check">
                        {{ $isPending ? 'Approve broker' : 'Re-approve broker' }}
                    </x-button>
                </form>
            @endif
        </div>
    </div>

    @unless($isPending)
        <div class="flex items-start gap-3 rounded-xl bg-canvas ring-1 ring-inset ring-line px-4 py-3 mb-5">
            <x-icon name="clock" class="w-4 h-4 text-ink-3 shrink-0 mt-0.5" />
            <p class="text-[12.5px] text-ink-2 leading-relaxed">
                <span class="font-medium text-ink">This registration has already been decided.</span>
                You can still change it — every decision is appended to the history below, so the
                earlier outcome and its reason are kept.
            </p>
        </div>
    @endunless

    @if($isPending)
        <div class="flex items-start gap-3 rounded-xl bg-warning-soft ring-1 ring-inset ring-warning-ring px-4 py-3 mb-5">
            <x-icon name="shield" class="w-4 h-4 text-warning shrink-0 mt-0.5" />
            <p class="text-[12.5px] text-ink-2 leading-relaxed">
                <span class="font-medium text-ink">Verify the RERA number against the regulator's registry</span>
                before approving — approval grants mobile-app access immediately.
            </p>
        </div>
    @endif

    @if(! $profile)
        <div class="flex items-start gap-3 rounded-xl bg-danger-soft ring-1 ring-inset ring-danger-ring px-4 py-3 mb-5">
            <x-icon name="x" class="w-4 h-4 text-danger shrink-0 mt-0.5" />
            <p class="text-[12.5px] text-ink-2 leading-relaxed">
                <span class="font-medium text-ink">No registration profile is attached to this account.</span>
                Only the login details below are available.
            </p>
        </div>
    @endif

    {{-- ============================== Summary ============================== --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-5">
        <x-stat-card icon="clock" label="Experience"
                     :value="$profile?->years_of_experience ? $profile->years_of_experience . ' yrs' : '—'" />
        <x-stat-card icon="users" label="Team size" :value="$profile?->team_size ?: '—'" />
        <x-stat-card icon="list" label="Segments" :value="count($profile?->segments ?? [])" />
        <x-stat-card icon="download" label="Documents" :value="$attached . ' of ' . count($documents)" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">
        <div class="xl:col-span-2 space-y-4">

            {{-- ---------------------------- Contact ---------------------------- --}}
            {{-- Plain "&": Blade escapes the prop when it renders, so a pre-escaped
                 "&amp;" here would reach the page as a literal "&amp;". --}}
            <x-panel title="Contact & identity" flush>
                <dl class="divide-y divide-line-soft">
                    @foreach([
                        'Full name' => $broker->name,
                        'Email' => $broker->email,
                        'Mobile' => $broker->mobile,
                        'Alternate mobile' => $profile?->alternate_mobile,
                        'Residence address' => $profile?->residence_address,
                    ] as $label => $value)
                        <div class="px-5 py-3 flex items-start gap-4">
                            <dt class="text-[12.5px] text-ink-3 w-[160px] shrink-0">{{ $label }}</dt>
                            <dd class="text-[13px] text-ink min-w-0 break-words whitespace-pre-line">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-panel>

            {{-- ---------------------------- Business ---------------------------- --}}
            <x-panel title="Business" flush>
                <dl class="divide-y divide-line-soft">
                    <div class="px-5 py-3 flex items-start gap-4">
                        <dt class="text-[12.5px] text-ink-3 w-[160px] shrink-0">Registering as</dt>
                        <dd class="text-[13px] text-ink">{{ $profile?->is_company ? 'Company' : 'Individual' }}</dd>
                    </div>
                    @foreach([
                        'Company name' => $profile?->company_name,
                        'Office address' => $profile?->office_address,
                        'Website' => $profile?->company_website,
                        'Social media' => $profile?->social_media_handle,
                        'Years of experience' => $profile?->years_of_experience,
                        'Team size' => $profile?->team_size,
                    ] as $label => $value)
                        <div class="px-5 py-3 flex items-start gap-4">
                            <dt class="text-[12.5px] text-ink-3 w-[160px] shrink-0">{{ $label }}</dt>
                            <dd class="text-[13px] text-ink min-w-0 break-words whitespace-pre-line">
                                @if($label === 'Website' && filled($value))
                                    <a href="{{ Str::startsWith($value, ['http://', 'https://']) ? $value : 'https://' . $value }}"
                                       target="_blank" rel="noopener noreferrer"
                                       class="text-primary-dark hover:underline inline-flex items-center gap-1">
                                        {{ $value }} <x-icon name="external" class="w-3.5 h-3.5" />
                                    </a>
                                @else
                                    {{ filled($value) ? $value : '—' }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-panel>

            {{-- ---------------------------- Coverage ---------------------------- --}}
            <x-panel title="Coverage" flush>
                <dl class="divide-y divide-line-soft">
                    @foreach(['State' => $profile?->state, 'City' => $profile?->city] as $label => $value)
                        <div class="px-5 py-3 flex items-start gap-4">
                            <dt class="text-[12.5px] text-ink-3 w-[160px] shrink-0">{{ $label }}</dt>
                            <dd class="text-[13px] text-ink">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach

                    <div class="px-5 py-3 flex items-start gap-4">
                        <dt class="text-[12.5px] text-ink-3 w-[160px] shrink-0">Multiple states</dt>
                        <dd class="text-[13px] text-ink">{{ $profile?->operates_multiple_states ? 'Yes' : 'No' }}</dd>
                    </div>

                    <div class="px-5 py-3 flex items-start gap-4">
                        <dt class="text-[12.5px] text-ink-3 w-[160px] shrink-0">Segments</dt>
                        <dd class="min-w-0">
                            @if($profile?->segments)
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($profile->segments as $segment)
                                        <x-badge tone="primary" size="sm">{{ $segment }}</x-badge>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-[13px] text-ink">—</span>
                            @endif
                        </dd>
                    </div>

                    <div class="px-5 py-3 flex items-start gap-4">
                        <dt class="text-[12.5px] text-ink-3 w-[160px] shrink-0">Operating zones</dt>
                        <dd class="min-w-0">
                            @if($profile?->zones)
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($profile->zones as $zone)
                                        <x-badge tone="neutral" size="sm">{{ $zone }}</x-badge>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-[13px] text-ink">—</span>
                            @endif
                        </dd>
                    </div>

                    <div class="px-5 py-3 flex items-start gap-4">
                        <dt class="text-[12.5px] text-ink-3 w-[160px] shrink-0">Project contributions</dt>
                        <dd class="text-[13px] text-ink min-w-0 break-words whitespace-pre-line">
                            {{ $profile?->project_contributions ?: '—' }}
                        </dd>
                    </div>
                </dl>
            </x-panel>
        </div>

        <div class="space-y-4">

            {{-- ---------------------------- Documents ---------------------------- --}}
            <x-panel title="Documents" flush>
                <x-slot:actions>
                    <span class="text-[11.5px] text-ink-3 nums">{{ $attached }}/{{ count($documents) }} attached</span>
                </x-slot:actions>

                <ul class="divide-y divide-line-soft">
                    @foreach($documents as $doc)
                        <li class="px-5 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[12.5px] font-medium text-ink">{{ $doc['label'] }}</p>
                                    @if(filled($doc['number']))
                                        <p class="text-[11.5px] text-ink-2 font-mono mt-0.5 break-all">{{ $doc['number'] }}</p>
                                    @endif
                                </div>

                                @if(filled($doc['path']))
                                    <a href="{{ Storage::disk('public')->url($doc['path']) }}"
                                       target="_blank" rel="noopener noreferrer"
                                       class="shrink-0 inline-flex items-center gap-1 text-[11.5px] font-medium
                                              text-primary-dark hover:underline">
                                        View <x-icon name="external" class="w-3.5 h-3.5" />
                                    </a>
                                @else
                                    <span class="shrink-0 text-[11.5px] text-ink-3">Not provided</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if($profile?->rera_certificate_expiry)
                    <div class="px-5 py-3 border-t border-line-soft flex items-center justify-between gap-3">
                        <span class="text-[12.5px] text-ink-3">RERA expiry</span>
                        <span @class([
                            'text-[12.5px] nums font-medium',
                            'text-danger' => $profile->rera_certificate_expiry->isPast(),
                            'text-ink' => ! $profile->rera_certificate_expiry->isPast(),
                        ])>
                            {{ $profile->rera_certificate_expiry->format('d M Y') }}
                            @if($profile->rera_certificate_expiry->isPast()) · expired @endif
                        </span>
                    </div>
                @endif
            </x-panel>

            {{-- ---------------------------- Declaration ---------------------------- --}}
            <x-panel title="Declaration" padded>
                <div class="flex items-start gap-2.5">
                    <x-icon :name="$profile?->confirm_accuracy ? 'check' : 'x'"
                            @class([
                                'w-4 h-4 shrink-0 mt-0.5',
                                'text-success' => $profile?->confirm_accuracy,
                                'text-ink-3' => ! $profile?->confirm_accuracy,
                            ]) />
                    <p class="text-[12.5px] text-ink-2 leading-relaxed">
                        {{ $profile?->confirm_accuracy
                            ? 'The broker confirmed the information given is accurate.'
                            : 'The accuracy declaration was not confirmed.' }}
                    </p>
                </div>
            </x-panel>

            {{-- ---------------------------- History ---------------------------- --}}
            <x-panel title="Decision history" flush>
                @forelse($broker->approvalDecisions as $decision)
                    <div class="px-5 py-3.5 border-b border-line-soft last:border-b-0">
                        <div class="flex items-center justify-between gap-3">
                            <x-badge :tone="$decision->decision === 'approved' ? 'success' : 'danger'" size="sm" dot>
                                {{ ucfirst($decision->decision) }}
                            </x-badge>
                            <span class="text-[11.5px] text-ink-3 nums">{{ $decision->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        <p class="text-[11.5px] text-ink-3 mt-1.5">
                            by {{ $decision->decider?->name ?? 'Unknown' }}
                        </p>
                        @if($decision->reason)
                            <p class="text-[12.5px] text-ink-2 mt-1.5 leading-relaxed">
                                <span class="text-ink-3">Reason:</span> {{ $decision->reason }}
                            </p>
                        @endif
                        @if($decision->internal_note)
                            <p class="text-[12.5px] text-ink-2 mt-1 leading-relaxed">
                                <span class="text-ink-3">Note:</span> {{ $decision->internal_note }}
                            </p>
                        @endif
                    </div>
                @empty
                    <p class="px-5 py-4 text-[12.5px] text-ink-3">No decision recorded yet.</p>
                @endforelse
            </x-panel>
        </div>
    </div>

    {{-- ============================== Reject dialog ==============================
         Replaces window.prompt(), which the queue used to collect the reason — an
         unstyled browser box that cannot enforce the field the controller requires. --}}
    {{-- Gated to match the button that opens it: available unless already rejected, so an
         approved broker's access can be revoked from here too. --}}
    @if($broker->status !== \App\Models\User::STATUS_REJECTED)
        <div x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }"
             x-on:open-reject.window="open = true"
             x-show="open" x-cloak
             @keydown.escape.window="open = false"
             class="fixed inset-0 z-[55] flex items-start justify-center px-4 py-10 overflow-y-auto"
             role="dialog" aria-modal="true" aria-labelledby="reject-title">

            <div x-show="open" x-transition.opacity @click="open = false" class="fixed inset-0 bg-scrim"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-3 scale-[0.98]"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 class="relative bg-panel rounded-2xl w-full max-w-md shadow-modal my-auto">

                <header class="px-6 py-4 border-b border-line flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h3 id="reject-title" class="text-[15px] font-semibold text-ink tracking-[-0.01em]">
                            {{ $isPending ? 'Reject this registration' : 'Revoke this broker’s access' }}
                        </h3>
                        <p class="text-[12.5px] text-ink-3 mt-0.5">
                            {{ $broker->name }} will not be able to sign in
                            @unless($isPending) and any active session is signed out @endunless.
                            The reason is stored on the audit trail.
                        </p>
                    </div>
                    <button type="button" @click="open = false" aria-label="Close dialog"
                            class="text-ink-3 hover:text-ink hover:bg-canvas rounded-lg p-1.5 -m-1 transition-colors shrink-0">
                        <x-icon name="x" class="w-4.5 h-4.5" />
                    </button>
                </header>

                <form method="POST" action="{{ route('admin.approvals.reject', $broker) }}" class="px-6 py-5 space-y-4">
                    @csrf
                    <x-field label="Reason for rejection" name="reason" type="textarea" rows="3" required
                             placeholder="e.g. RERA number could not be verified against the registry." />
                    <x-field label="Internal note" name="internal_note" type="textarea" rows="2"
                             placeholder="Optional — visible to admins only."
                             hint="Not shared with the broker." />

                    <div class="pt-1 flex items-center justify-end gap-2.5">
                        <button type="button" @click="open = false"
                                class="h-9 px-4 rounded-lg text-[13px] font-medium text-ink-2 hover:text-ink hover:bg-line-soft transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                class="h-9 px-4 rounded-lg bg-danger text-white text-[13px] font-medium hover:brightness-110 transition-all">
                            Reject registration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-layouts.admin>
