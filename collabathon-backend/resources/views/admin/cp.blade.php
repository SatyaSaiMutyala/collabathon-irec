<x-layouts.admin active="cp" title="Channel Partners" section="Manage">

    <x-page-header
        title="Channel Partners"
        subtitle="Approved partners who can sign in. New registrations go through the approval queue first.">
        <x-slot:actions>
            <x-button variant="subtle" tag="a" icon="clock" href="{{ route('admin.approvals') }}">
                Approval queue
            </x-button>

            {{-- The one-at-a-time counterpart to bulk upload below — same "already
                 vetted, lands active immediately" outcome, for the common case of
                 adding a single partner rather than a whole roster. --}}
            <x-modal title="Add channel partner"
                     subtitle="A login account is created — they sign in with email/mobile and an OTP, same as every channel partner."
                     width="max-w-2xl"
                     :open="$errors->any() && old('_form') === 'cp-create'">
                <x-slot:trigger>
                    <x-button variant="gold" icon="plus">Add channel partner</x-button>
                </x-slot:trigger>

                {{-- No password field: a broker account signs in with email/mobile + OTP,
                     never a password — see AuthController::startRegistration's own note.

                     Every required field here (name/mobile/email) stays visible regardless
                     of the company toggle, so native validation runs first and this handler
                     only fires once the form is valid — same reasoning as the developer
                     create form's own note. Compresses the photo and every KYC document the
                     same way a developer's logo is compressed, since a phone-camera photo of
                     a PAN or Aadhaar card is exactly the kind of file that needs it. --}}
                <form method="POST" action="{{ route('admin.cp.store') }}" enctype="multipart/form-data"
                      class="space-y-5"
                      x-data="{ isCompany: {{ old('is_company') ? 'true' : 'false' }}, busy: false }"
                      x-on:submit="
                          $event.preventDefault();
                          busy = true;
                          Promise.resolve(window.compressFileInputs?.($el))
                              .catch((error) => console.error('Attachment compression failed; uploading originals.', error))
                              .finally(() => {
                                  window.dispatchEvent(new CustomEvent('navigate-start'));
                                  $el.submit();
                              });
                      ">
                    @csrf
                    <input type="hidden" name="_form" value="cp-create">

                    {{-- A native file input can never be repopulated by old() — see the
                         matching note on x-file-field. If this reopened after a validation
                         error, any photo/document already picked is gone, and re-submitting
                         as-is silently saves the partner with none attached. --}}
                    @if($errors->any() && old('_form') === 'cp-create')
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
                        <x-field label="Full name" name="name" placeholder="e.g. Rahul Verma" required />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Mobile number" name="mobile" placeholder="10-digit mobile" icon="phone" required />
                            <x-field label="Alternate mobile" name="alternate_mobile" placeholder="Optional" icon="phone" />
                        </div>

                        <x-field label="Email" name="email" type="email" placeholder="name@example.com" icon="mail" required
                                  hint="This becomes their login." />

                        <x-field label="Residence address" name="residence_address" type="textarea" rows="2"
                                  placeholder="Flat / street / area / city / pincode" />

                        <x-file-field label="Photo" name="photo" accept="image/*"
                                      hint="Passport-size photo — shown as their avatar across the app." />
                    </div>

                    {{-- Business ------------------------------------------------------ --}}
                    <div class="border-t border-line-soft space-y-3 pt-4">
                        <x-switch-field label="Registering as a company?" name="is_company" x-model="isCompany" />

                        <div x-show="isCompany" x-cloak class="space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <x-field label="Company name" name="company_name" placeholder="Firm name" />
                                <x-field label="Company website" name="company_website" placeholder="https://..." />
                            </div>
                            <x-field label="Office address" name="office_address" placeholder="Office location" />

                            <div>
                                <p class="text-[12.5px] font-medium text-ink mb-1.5">Social media</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach(\App\Support\SocialPlatforms::ALL as $key => $label)
                                        <x-field :label="$label" :name="$key" placeholder="@handle or profile URL" />
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Location & coverage -------------------------------------------- --}}
                    <div class="border-t border-line-soft space-y-3 pt-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="City" name="city" placeholder="Hyderabad" />
                            <x-field label="State" name="state" placeholder="Telangana" />
                        </div>

                        {{-- Same category/zone vocabulary the mobile app's own
                             registration form offers — see CompleteProfileScreen's
                             SEGMENT_OPTIONS / ZONE_OPTIONS. --}}
                        <x-checkbox-group label="Categories" name="segments" columns="3"
                                           :options="['Residential', 'Commercial', 'Lands', 'Liaisoning', 'All']" />
                        <x-checkbox-group label="Zones" name="zones" columns="3"
                                           :options="['East', 'West', 'North', 'South', 'Central', 'All']" />

                        <x-field label="Project contributions" name="project_contributions" type="textarea" rows="2"
                                  placeholder="Notable projects worked on" />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Years of experience" name="years_of_experience" type="number" placeholder="e.g. 5" />
                            <x-field label="Team size" name="team_size" type="number" placeholder="e.g. 4" />
                        </div>
                    </div>

                    {{-- KYC ------------------------------------------------------------ --}}
                    <div class="border-t border-line-soft space-y-3 pt-4">
                        <div class="flex items-center gap-2">
                            <h4 class="text-[13px] font-semibold text-ink">KYC & compliance</h4>
                            <x-badge tone="neutral" size="sm">Optional</x-badge>
                        </div>

                        <x-field label="RERA number" name="rera_number" placeholder="A02400012345" />
                        <x-file-field label="RERA certificate" name="rera_certificate_file" accept=".pdf,image/*"
                                      hint="PDF or a photo of the certificate." />

                        <x-field label="PAN card number" name="pan_card" placeholder="ABCDE1234F" />
                        <x-file-field label="PAN card copy" name="pan_card_file" accept=".pdf,image/*"
                                      hint="PDF or a photo of the card." />

                        <x-field label="Aadhaar number" name="aadhaar_card" placeholder="XXXX XXXX XXXX" />
                        <x-file-field label="Aadhaar copy" name="aadhaar_file" accept=".pdf,.xml,image/*"
                                      hint="PDF, UIDAI offline XML, or a photo of the card." />

                        <x-field label="GST number" name="gst_number" placeholder="Optional" />
                        <x-file-field label="GST certificate" name="gst_file" accept=".pdf,image/*" hint="Optional." />
                    </div>

                    <div class="pt-1">
                        <x-button variant="gold" tag="button" type="submit" icon="check" class="w-full" x-bind:disabled="busy">
                            <span x-show="! busy">Add channel partner</span>
                            <span x-show="busy" x-cloak>Optimising attachments…</span>
                        </x-button>
                    </div>
                </form>
            </x-modal>

            {{-- The one way a partner arrives here without passing through the approvals
                 queue: a roster the admin has already vetted offline. --}}
            <x-modal title="Bulk upload channel partners"
                     subtitle="One row per partner. They land approved and able to sign in — KYC scans aren't part of the sheet, so add those on each partner's page afterward."
                     width="max-w-lg"
                     :open="$errors->any() && old('_form') === 'bulk-import'">
                <x-slot:trigger>
                    <x-button variant="outline" icon="upload">Bulk upload</x-button>
                </x-slot:trigger>

                <form method="POST" action="{{ route('admin.cp.bulk-import') }}"
                      enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="hidden" name="_form" value="bulk-import">

                    {{-- The template download is a button, not a link inside the sentence:
                         it is the first thing to do here, and a client reading the panel
                         should not have to find it inside a paragraph. --}}
                    <div class="rounded-lg bg-canvas border border-line px-3.5 py-3">
                        <p class="text-[12.5px] text-ink-2 leading-relaxed">
                            Start from the template so the column names match exactly.
                            <span class="text-ink-3">Name, email and a 10-digit mobile are required per row;
                                everything else is optional. Categories and zones take several values in one
                                cell, separated by <span class="font-mono">|</span>.</span>
                        </p>

                        {{-- `download` is load-bearing, not decoration: the layout's click
                             handler puts the page into its loading skeleton for any link
                             without it, and a file download fires no pageshow to clear it
                             again — the panel would sit there pretending to navigate. --}}
                        <x-button variant="outline" size="sm" tag="a" icon="download" class="mt-3" download
                                  href="{{ route('admin.cp.bulk-import.template') }}">
                            Download CSV template
                        </x-button>
                    </div>

                    <x-file-field label="CSV file" name="file" accept=".csv,text/csv" required
                                  hint="Exported from Excel/Sheets as CSV — .xlsx is not read directly." />

                    <x-button variant="gold" tag="button" type="submit" icon="upload" class="w-full">
                        Upload &amp; create
                    </x-button>
                </form>
            </x-modal>

            <x-export-menu :export="$export" />
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-5">
        <x-stat-card icon="users" label="Active partners" :value="$stats['total']" />
        <x-stat-card icon="building" label="Registered as company" :value="$stats['companies']" />
        <x-stat-card icon="sparkles" label="Joined (30d)" :value="$stats['joined_30d']" />
        <x-stat-card icon="list" label="Cities covered" :value="$stats['cities']" />
    </div>

    <x-data-table
        :paginator="$partners"
        label="partners"
        search-placeholder="Search by name, company, email, mobile or RERA…"
        empty-icon="users"
        empty-title="No channel partners yet"
        empty-description="Approved channel partners appear here. Try clearing the filters, or review the approval queue.">

        <x-slot:filters>
            <x-filter-select name="city" :options="$cities" placeholder="All cities" />
            <x-filter-select name="state" :options="$states" placeholder="All states" />
            <x-filter-select name="segment" :options="$segments" placeholder="All categories" />
            <x-filter-select name="type"
                             :options="['company' => 'Company', 'individual' => 'Individual']"
                             placeholder="Any type" />
        </x-slot:filters>

        <x-slot:head>
            <x-th sort="name">Partner</x-th>
            <x-th hide="lg">Contact</x-th>
            <x-th hide="md">RERA</x-th>
            <x-th hide="xl">Categories</x-th>
            <x-th sort="city" hide="lg">Location</x-th>
            <x-th sort="created_at" hide="xl">Joined</x-th>
            <x-th hide="lg">Signed in on</x-th>
            <x-th>Status</x-th>
            <x-th align="right">Listings</x-th>
            <x-th align="right"><span class="sr-only">Actions</span></x-th>
        </x-slot:head>

        @foreach($partners as $partner)
            @php $profile = $partner->brokerProfile; @endphp
            {{-- Opens the same review page the approvals queue uses — an active partner's
                 paperwork is the same record, just at a later stage. --}}
            <tr class="hover:bg-canvas transition-colors cursor-pointer"
                x-on:click="if (! $event.target.closest('[data-row-actions]')) window.location = @js(route('admin.approvals.show', [$partner, 'from' => 'cp']))">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-avatar :name="$partner->name" :src="$profile?->photo_path" size="md" />
                        <div class="min-w-0">
                            <a href="{{ route('admin.approvals.show', [$partner, 'from' => 'cp']) }}"
                               class="text-[13px] font-medium text-ink hover:underline truncate block">{{ $partner->name }}</a>
                            <p class="text-[11.5px] text-ink-3 truncate">
                                {{ $profile?->company_name ?: 'Individual' }}
                            </p>
                        </div>
                    </div>
                </td>

                <td class="px-4 py-3 hidden lg:table-cell">
                    <p class="text-[12.5px] text-ink-2 nums">{{ $partner->mobile }}</p>
                    <p class="text-[11.5px] text-ink-3 truncate">{{ $partner->email }}</p>
                </td>

                <td class="px-4 py-3 hidden md:table-cell">
                    <span class="text-[12.5px] text-ink-2 nums">{{ $profile?->rera_number ?: '—' }}</span>
                </td>

                <td class="px-4 py-3 hidden xl:table-cell">
                    <div class="flex flex-wrap gap-1">
                        @foreach(($profile?->segments ?? []) as $segment)
                            <x-badge tone="neutral" size="sm">{{ $segment }}</x-badge>
                        @endforeach
                    </div>
                </td>

                <td class="px-4 py-3 hidden lg:table-cell">
                    <p class="text-[12.5px] text-ink-2 truncate">{{ $profile?->city ?: '—' }}</p>
                    <p class="text-[11.5px] text-ink-3 truncate">{{ $profile?->state ?: '' }}</p>
                </td>

                <td class="px-4 py-3 hidden xl:table-cell">
                    <span class="text-[12.5px] text-ink-3 nums">{{ $partner->created_at->format('d M Y') }}</span>
                </td>

                {{-- The one active session. Channel partners are single-device: every
                     sign-in revokes the account's other tokens (AuthController::issueToken),
                     so a name here is the handset they are on right now. "Not signed in"
                     means the account is approved but nobody has ever logged in with it —
                     worth seeing on a roster of people who are supposed to be selling. --}}
                <td class="px-4 py-3 hidden lg:table-cell">
                    @php $session = $partner->tokens->first(); @endphp
                    @if($session)
                        <p class="text-[12.5px] text-ink-2 truncate max-w-[190px]"
                           title="{{ $session->name }}">{{ $session->name }}</p>
                        <p class="text-[11.5px] text-ink-3">
                            {{-- last_used_at stays null until the app makes its first
                                 authenticated call, a moment after sign-in — falling back
                                 to created_at avoids a blank row for someone who just
                                 logged in. --}}
                            {{ ($session->last_used_at ?? $session->created_at)->diffForHumans() }}
                        </p>
                    @else
                        <span class="text-[12.5px] text-ink-3">Not signed in</span>
                    @endif
                </td>

                <td class="px-4 py-3">
                    @if($partner->status === \App\Models\User::STATUS_INACTIVE)
                        <x-badge tone="neutral" size="sm" dot>Inactive</x-badge>
                        @if($partner->deleted_at)
                            <p class="text-[10.5px] text-ink-3 mt-1">Deleted {{ $partner->deleted_at->diffForHumans() }}</p>
                        @endif
                    @else
                        <x-badge tone="success" size="sm" dot>Active</x-badge>
                    @endif
                </td>

                <td class="px-4 py-3 text-right">
                    <span class="text-[12.5px] text-ink-2 nums">{{ $partner->accepted_leads_count }}</span>
                </td>

                {{-- data-row-actions stops the row's click-through firing in here. --}}
                <td class="px-4 py-3 text-right" data-row-actions>
                    @php
                        // The lead count is in the prompt rather than being a nasty
                        // surprise — leads cascade on the broker, same as the documents.
                        $deletePayload = \Illuminate\Support\Js::from([
                            'title' => 'Delete this channel partner?',
                            'message' => "{$partner->name}, their documents and "
                                . $partner->accepted_leads_count . ' accepted lead'
                                . ($partner->accepted_leads_count === 1 ? '' : 's')
                                . ' will be permanently deleted. This cannot be undone.',
                            'confirmLabel' => 'Delete channel partner',
                            'tone' => 'danger',
                        ]);
                    @endphp

                    <form method="POST" action="{{ route('admin.cp.destroy', $partner) }}"
                          x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $deletePayload }}, form: $el })">
                        @csrf @method('DELETE')
                        <x-button variant="danger-ghost" size="sm" icon="x" tag="button" type="submit"
                                  aria-label="Delete {{ $partner->name }}" />
                    </form>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
