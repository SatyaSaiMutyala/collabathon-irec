@php
    $isPending = $broker->status === \App\Models\User::STATUS_PENDING;

    $statusTone = match ($broker->status) {
        \App\Models\User::STATUS_ACTIVE => 'success',
        \App\Models\User::STATUS_PENDING => 'warning',
        default => 'danger',
    };

    $resetPayload = \Illuminate\Support\Js::from([
        'name' => $broker->name,
        'action' => route('admin.approvals.password', $broker),
    ]);

    // A failed edit redirects back here, so the dialog reopens with what was typed
    // rather than silently discarding it — same convention as developers/show.blade.php.
    $editReopen = $errors->any() && old('_form') === 'cp-edit';

    /**
     * Aadhaar is the one document number sensitive enough to keep off-screen even
     * from an admin reviewing the registration — the document itself (viewable via
     * its own link below) is still the actual verification evidence; this is just
     * the typed-in number shown for a quick eyeball match. Normalises to the
     * standard "XXXX XXXX 1234" masked form regardless of how it was typed
     * (with or without spaces).
     */
    $maskAadhaar = function (?string $number) {
        if (blank($number)) {
            return $number;
        }

        $digits = preg_replace('/\D/', '', $number);
        if ($digits === '' || strlen($digits) <= 4) {
            return $number;
        }

        return 'XXXX XXXX ' . substr($digits, -4);
    };

    /**
     * Every uploaded document, in the order the mobile app collects them. Paths are
     * frequently null — a registration can reach the queue with none attached — so each
     * row renders either a link or a plain "Not provided".
     *
     * Cancelled cheque is the one deliberate exclusion: the registration form no longer
     * produces it — neither `cheque_details` nor `cheque_file` is in the app's payload any
     * more — and a row that can never be filled reads as a missing document rather than an
     * absent one. The column is left on `broker_profiles`; existing registrations still
     * hold data.
     *
     * Signature IS listed below even though it is `null` for every registration today —
     * the form still asks for one and the API already accepts `signature_file`, but the
     * SignaturePad only tracks a `hasSignature` boolean and never actually uploads the
     * drawn image (a real mobile-app gap, not an admin display one). Showing it as "Not
     * provided" here is the honest state, same as any other document nobody attached.
     */
    $documents = [
        [
            'label' => 'PAN card',
            'number' => $profile?->pan_card,
            'path' => $profile?->pan_card_path,
            // Checked against Surepass at registration time, same as Aadhaar below —
            // see PanVerificationService / KycController::verifyPan.
            'verified' => (bool) $profile?->pan_verified,
            'verified_name' => $profile?->pan_verified_name,
        ],
        [
            'label' => 'Aadhaar card',
            'number' => $maskAadhaar($profile?->aadhaar_card),
            'path' => $profile?->aadhaar_path,
            // Checked live via DigiLocker at registration time — see
            // DigilockerVerificationService / DigilockerController::downloadAadhaar.
            'verified' => (bool) $profile?->aadhaar_verified,
            'verified_name' => $profile?->aadhaar_verified_name,
            // A DigiLocker verification attaches the actual UIDAI-signed XML, not a
            // photo/PDF — opening that raw file shows nothing an admin can read (every
            // browser just dumps the tag tree), so it routes through a formatted
            // preview instead. A manually-attached photo/PDF still opens directly.
            'view_url' => \Illuminate\Support\Str::endsWith((string) $profile?->aadhaar_path, '.xml')
                ? route('admin.approvals.aadhaar-preview', $broker)
                : null,
        ],
        ['label' => 'RERA certificate', 'number' => $profile?->rera_number, 'path' => $profile?->rera_certificate_path],
        ['label' => 'GST certificate', 'number' => $profile?->gst_number, 'path' => $profile?->gst_path],
        ['label' => 'Authorized signature', 'number' => null, 'path' => $profile?->signature_path],
    ];

    $attached = collect($documents)->filter(fn ($d) => filled($d['path']))->count();

    $rejectPayload = \Illuminate\Support\Js::from([
        'name' => $broker->name,
        'action' => route('admin.approvals.reject', $broker),
    ]);

    /**
     * x-detail-grid renders an HtmlString as markup, which is how the two fields that are
     * really lists of chips, and the one that is a link, keep their formatting without the
     * component needing a slot per special case. Both helpers escape their own input.
     */
    $badges = function (?array $items, string $tone) {
        if (! $items) {
            return null;
        }

        $chips = collect($items)
            ->map(fn ($item) => '<span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11.5px] font-medium '
                . ($tone === 'primary' ? 'bg-primary-soft text-primary-dark' : 'bg-canvas text-ink-2 ring-1 ring-inset ring-line')
                . '">' . e($item) . '</span>')
            ->implode('');

        return new \Illuminate\Support\HtmlString('<span class="flex flex-wrap gap-1.5">' . $chips . '</span>');
    };

    $website = filled($profile?->company_website)
        ? new \Illuminate\Support\HtmlString(sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer" class="text-primary-dark hover:underline break-all">%s</a>',
            e(\Illuminate\Support\Str::startsWith($profile->company_website, ['http://', 'https://'])
                ? $profile->company_website
                : 'https://' . $profile->company_website),
            e($profile->company_website)
        ))
        : null;

    $socialLinks = $profile ? \App\Support\SocialPlatforms::linksFor($profile) : [];
    $socialCell = count($socialLinks)
        ? new \Illuminate\Support\HtmlString(
            collect($socialLinks)->map(fn ($link) => sprintf(
                '<a href="%s" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-primary-dark hover:underline"><span class="inline-flex shrink-0">%s</span><span>%s</span></a>',
                e(\Illuminate\Support\Str::startsWith($link['value'], ['http://', 'https://'])
                    ? $link['value']
                    : 'https://' . ltrim($link['value'], '@')),
                // $link['key'] is one of SocialPlatforms::ALL's keys, which is also
                // exactly the matching icon's name in icon.blade.php.
                view('components.icon', ['name' => $link['key'], 'class' => 'w-3.5 h-3.5 shrink-0'])->render(),
                e($link['label'])
            ))->join('<span class="text-ink-3 mx-0.5">·</span>')
        )
        : null;
@endphp

{{--
    Approve, Reject and Edit refresh this whole block in place instead of navigating away
    — `id="approval-detail"` is what app.js's `#approval-detail` fetch mechanism targets
    (see the note there). ApprovalController::show()'s ajax() branch renders this exact
    partial with no surrounding layout, so a fetch-and-swap can never drift from a real
    page load. Reset password is deliberately NOT part of that contract: its dialog is a
    layout-level component shared with Team/Developers (`<x-reset-password-dialog />` in
    layouts/admin.blade.php), outside this element entirely, and its success flow reveals
    a generated password through session-flashed `credentials` — changing that to a fetch
    would mean reworking how every page using it reveals a password, not just this one.
--}}
<div id="approval-detail">

    <a href="{{ route('admin.approvals') }}"
       class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2 hover:text-ink transition-colors mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" />
        Back to channel partner approvals
    </a>

    {{-- ============================== Header ============================== --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div class="flex items-start gap-3.5 flex-1 min-w-[260px]">
            @if($profile?->photo_path)
                <img src="{{ \App\Support\FileStorage::url($profile->photo_path) }}" alt=""
                     class="w-14 h-14 rounded-xl object-cover border border-line-soft shrink-0">
            @else
                <x-avatar :name="$broker->name" :src="$profile?->photo_path" size="lg" class="w-14 h-14 shrink-0" />
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
                    {{ $profile?->company_name ?: 'Independent channel partner' }}
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
            <x-button variant="gold" icon="cog" tag="button" type="button" x-on:click="$dispatch('open-cp-edit')">
                Edit
            </x-button>

            {{-- Offered at every status: a broker who cannot sign in to be approved is
                 as stuck as one who forgot their password after approval. --}}
            <x-button variant="subtle" icon="lock" tag="button" type="button"
                      x-on:click="$dispatch('reset-password', {{ $resetPayload }})">
                Reset password
            </x-button>

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
                        {{ $isPending ? 'Approve channel partner' : 'Re-approve channel partner' }}
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
        {{-- Labelled "Categories" to match the Channel Partners screen. The underlying
             column, the API field and the mobile app all still call it `segments` —
             only the admin-facing wording changed. --}}
        <x-stat-card icon="list" label="Categories" :value="count($profile?->segments ?? [])" />
        <x-stat-card icon="download" label="Documents" :value="$attached . ' of ' . count($documents)" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">
        <div class="xl:col-span-2 space-y-4">

            {{-- ---------------------------- Contact ---------------------------- --}}
            {{-- Plain "&": Blade escapes the prop when it renders, so a pre-escaped
                 "&amp;" here would reach the page as a literal "&amp;". --}}
            <x-panel title="Contact & identity" flush>
                <x-detail-grid :fields="[
                    ['label' => 'Full name', 'value' => $broker->name],
                    ['label' => 'Email', 'value' => $broker->email],
                    ['label' => 'Mobile', 'value' => $broker->mobile],
                    ['label' => 'Alternate mobile', 'value' => $profile?->alternate_mobile],
                    ['label' => 'Residence address', 'value' => $profile?->residence_address, 'wide' => true],
                ]" />
            </x-panel>

            {{-- ---------------------------- Business ---------------------------- --}}
            <x-panel title="Business" flush>
                <x-detail-grid :fields="[
                    ['label' => 'Registering as', 'value' => $profile?->is_company ? 'Company' : 'Individual'],
                    ['label' => 'Company name', 'value' => $profile?->company_name],
                    ['label' => 'Website', 'value' => $website],
                    ['label' => 'Social media', 'value' => $socialCell],
                    ['label' => 'Years of experience', 'value' => $profile?->years_of_experience],
                    ['label' => 'Team size', 'value' => $profile?->team_size],
                    ['label' => 'Office address', 'value' => $profile?->office_address, 'wide' => true],
                ]" />
            </x-panel>

            {{-- ---------------------------- Coverage ---------------------------- --}}
            <x-panel title="Coverage" flush>
                <x-detail-grid :fields="[
                    ['label' => 'State', 'value' => $profile?->state],
                    ['label' => 'City', 'value' => $profile?->city],
                    ['label' => 'Multiple states', 'value' => $profile?->operates_multiple_states ? 'Yes' : 'No'],
                    ['label' => 'Categories', 'value' => $badges($profile?->segments, 'primary')],
                    ['label' => 'Operating zones', 'value' => $badges($profile?->zones, 'neutral')],
                    ['label' => 'Project contributions', 'value' => $profile?->project_contributions, 'wide' => true],
                ]" />
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
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <p class="text-[12.5px] font-medium text-ink">{{ $doc['label'] }}</p>
                                        @if(array_key_exists('verified', $doc))
                                            <x-badge :tone="$doc['verified'] ? 'success' : 'neutral'" size="sm" :dot="true">
                                                {{ $doc['verified'] ? 'Verified' : 'Unverified' }}
                                            </x-badge>
                                        @endif
                                    </div>
                                    @if(filled($doc['number']))
                                        <p class="text-[11.5px] text-ink-2 font-mono mt-0.5 break-all">{{ $doc['number'] }}</p>
                                    @endif
                                    {{-- Surepass's own answer for the name on the card — shown next to
                                         whatever the broker typed elsewhere so an admin can eyeball a
                                         mismatch without cross-referencing a separate screen. --}}
                                    @if(($doc['verified'] ?? false) && filled($doc['verified_name'] ?? null))
                                        <p class="text-[11px] text-success mt-0.5">
                                            Matches: {{ $doc['verified_name'] }}
                                        </p>
                                    @endif
                                </div>

                                @if(filled($doc['path']))
                                    <a href="{{ $doc['view_url'] ?? \App\Support\FileStorage::url($doc['path']) }}"
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
                            ? 'The channel partner confirmed the information given is accurate.'
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

    {{-- ============================== Edit dialog ==============================
         Every field store()/{@see \App\Http\Controllers\Admin\ChannelPartnerController::update()}
         collects, pre-filled — the create-form fields from admin/cp.blade.php, mirrored
         here rather than shared as a component: one lives in a list-page modal building
         a brand-new record, this one edits an existing one and needs :value/:current on
         every field, close enough in shape but not close enough to be worth the
         indirection of a shared partial. Status and verification stay off this form —
         those are Approve/Reject/Reset password's job, not an edit's. --}}
    <div x-data="{ open: @js($editReopen) }"
         x-on:open-cp-edit.window="open = true"
         x-show="open" x-cloak
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-[55] flex items-start justify-center px-4 py-10 overflow-y-auto"
         role="dialog" aria-modal="true" aria-labelledby="cp-edit-title">

        <div x-show="open" x-transition.opacity @click="open = false" class="fixed inset-0 bg-scrim"></div>

        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-3 scale-[0.98]"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             class="relative bg-panel rounded-2xl w-full max-w-2xl shadow-modal my-auto">

            <header class="px-6 py-4 border-b border-line flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3 id="cp-edit-title" class="text-[15px] font-semibold text-ink tracking-[-0.01em]">Edit channel partner</h3>
                    <p class="text-[12.5px] text-ink-3 mt-0.5">Changing the email changes the login this partner signs in with.</p>
                </div>
                <button type="button" @click="open = false" aria-label="Close dialog"
                        class="text-ink-3 hover:text-ink hover:bg-canvas rounded-lg p-1.5 -m-1 transition-colors shrink-0">
                    <x-icon name="x" class="w-4.5 h-4.5" />
                </button>
            </header>

            {{-- `data-manual-submit` excludes this form from app.js's generic
                 `#approval-detail` submit interceptor — it needs to await file
                 compression BEFORE the fetch fires, so it drives the same
                 window.submitApprovalForm() fetch helper directly instead of letting
                 the generic listener grab the raw submit event first (which would
                 send the uncompressed files straight through, before compression had
                 a chance to run). Same reasoning as `data-confirm` on the Settings
                 page: a capture-phase document listener sees this event regardless
                 of what a handler further down calls preventDefault() on. --}}
            <form method="POST" action="{{ route('admin.cp.update', $broker) }}" enctype="multipart/form-data"
                  class="px-6 py-5 space-y-5"
                  data-manual-submit
                  x-data="{ isCompany: {{ old('is_company', (bool) $profile?->is_company) ? 'true' : 'false' }}, busy: false }"
                  x-on:submit="
                      $event.preventDefault();
                      busy = true;
                      Promise.resolve(window.compressFileInputs?.($el))
                          .catch((error) => console.error('Attachment compression failed; uploading originals.', error))
                          .finally(() => window.submitApprovalForm($el).finally(() => { busy = false; }));
                  ">
                @csrf @method('PATCH')
                <input type="hidden" name="_form" value="cp-edit">

                @if($errors->any() && old('_form') === 'cp-edit')
                    <div class="flex items-start gap-2.5 rounded-lg bg-warning-soft ring-1 ring-inset ring-warning-ring px-3.5 py-3">
                        <x-icon name="shield" class="w-4 h-4 text-warning shrink-0 mt-0.5" />
                        <p class="text-[12.5px] text-ink-2 leading-relaxed">
                            <span class="font-medium text-ink">Re-select any photo or documents below.</span>
                            File choices can't survive a failed save — everything else you typed is still here.
                        </p>
                    </div>
                @endif

                {{-- Personal ---------------------------------------------------- --}}
                <div class="space-y-3">
                    <x-field label="Full name" name="name" :value="$broker->name" required />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="Mobile number" name="mobile" :value="$broker->mobile" icon="phone" required />
                        <x-field label="Alternate mobile" name="alternate_mobile" :value="$profile?->alternate_mobile" icon="phone" />
                    </div>

                    <x-field label="Email" name="email" type="email" :value="$broker->email" icon="mail" required
                              hint="This is their login." />

                    <x-field label="Residence address" name="residence_address" type="textarea" rows="2"
                              :value="$profile?->residence_address" />

                    <x-file-field label="Photo" name="photo" accept="image/*" :current="$profile?->photo_path"
                                  hint="Passport-size photo — shown as their avatar across the app." />
                </div>

                {{-- Business ------------------------------------------------------ --}}
                <div class="border-t border-line-soft space-y-3 pt-4">
                    <x-switch-field label="Registering as a company?" name="is_company"
                                    :checked="(bool) $profile?->is_company" x-model="isCompany" />

                    <div x-show="isCompany" x-cloak class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Company name" name="company_name" :value="$profile?->company_name" />
                            <x-field label="Company website" name="company_website" :value="$profile?->company_website" />
                        </div>
                        <x-field label="Office address" name="office_address" :value="$profile?->office_address" />

                        <div>
                            <p class="text-[12.5px] font-medium text-ink mb-1.5">Social media</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach(\App\Support\SocialPlatforms::ALL as $key => $label)
                                    <x-field :label="$label" :name="$key" :value="$profile?->{$key}" placeholder="@handle or profile URL" />
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Location & coverage -------------------------------------------- --}}
                <div class="border-t border-line-soft space-y-3 pt-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="City" name="city" :value="$profile?->city" />
                        <x-field label="State" name="state" :value="$profile?->state" />
                    </div>

                    <x-checkbox-group label="Categories" name="segments" columns="3"
                                       :options="['Residential', 'Commercial', 'Lands', 'Liaisoning', 'All']"
                                       :selected="$profile?->segments ?? []" />
                    <x-checkbox-group label="Zones" name="zones" columns="3"
                                       :options="['East', 'West', 'North', 'South', 'Central', 'All']"
                                       :selected="$profile?->zones ?? []" />

                    <x-field label="Project contributions" name="project_contributions" type="textarea" rows="2"
                              :value="$profile?->project_contributions" />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="Years of experience" name="years_of_experience" type="number" :value="$profile?->years_of_experience" />
                        <x-field label="Team size" name="team_size" type="number" :value="$profile?->team_size" />
                    </div>
                </div>

                {{-- KYC ------------------------------------------------------------ --}}
                <div class="border-t border-line-soft space-y-3 pt-4">
                    <div class="flex items-center gap-2">
                        <h4 class="text-[13px] font-semibold text-ink">KYC & compliance</h4>
                        <x-badge tone="neutral" size="sm">Optional</x-badge>
                    </div>

                    <x-field label="RERA number" name="rera_number" :value="$profile?->rera_number" />
                    <x-file-field label="RERA certificate" name="rera_certificate_file" accept=".pdf,image/*"
                                  :current="$profile?->rera_certificate_path" hint="PDF or a photo of the certificate." />

                    <x-field label="PAN card number" name="pan_card" :value="$profile?->pan_card" />
                    <x-file-field label="PAN card copy" name="pan_card_file" accept=".pdf,image/*"
                                  :current="$profile?->pan_card_path" hint="PDF or a photo of the card." />

                    <x-field label="Aadhaar number" name="aadhaar_card" :value="$profile?->aadhaar_card" />
                    <x-file-field label="Aadhaar copy" name="aadhaar_file" accept=".pdf,.xml,image/*"
                                  :current="$profile?->aadhaar_path" hint="PDF, UIDAI offline XML, or a photo of the card." />

                    <x-field label="GST number" name="gst_number" :value="$profile?->gst_number" />
                    <x-file-field label="GST certificate" name="gst_file" accept=".pdf,image/*"
                                  :current="$profile?->gst_path" hint="Optional." />
                </div>

                <div class="pt-1 flex items-center justify-end gap-2.5">
                    <button type="button" @click="open = false"
                            class="h-9 px-4 rounded-lg text-[13px] font-medium text-ink-2 hover:text-ink hover:bg-line-soft transition-colors">
                        Cancel
                    </button>
                    <x-button variant="gold" tag="button" type="submit" icon="check" x-bind:disabled="busy">
                        <span x-show="! busy">Save changes</span>
                        <span x-show="busy" x-cloak>Saving…</span>
                    </x-button>
                </div>
            </form>
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
                            {{ $isPending ? 'Reject this registration' : 'Revoke this channel partner’s access' }}
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
                             hint="Not shared with the channel partner." />

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
</div>
