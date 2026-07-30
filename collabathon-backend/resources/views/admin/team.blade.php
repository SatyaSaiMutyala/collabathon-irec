<x-layouts.admin active="team" title="Team" section="Administration">

    <x-page-header
        title="Team"
        subtitle="Admin-side staff accounts. Each is assigned a role that controls what they can see and do.">
        <x-slot:actions>
            <x-modal title="Create team member"
                     subtitle="A login account is created with the password below."
                     width="max-w-lg"
                     :open="$errors->any()">
                <x-slot:trigger>
                    <x-button variant="gold" icon="plus">Add team member</x-button>
                </x-slot:trigger>

                <form method="POST" action="{{ route('admin.team.store') }}" class="space-y-4">
                    @csrf
                    <x-field label="Full name" name="name" placeholder="Full name" required
                             maxlength="255" autocomplete="off" />
                    <x-field label="Email" name="email" type="email" placeholder="name@company.ae" icon="mail" required
                             maxlength="255" autocomplete="off"
                             hint="This becomes their login." />

                    {{-- Pre-filled so it can be copied/shared before saving; the admin may
                         also type their own, or clear it to have one generated. --}}
                    <x-password-field hint="Shown once more after saving. They change it on first sign-in." />

                    <x-select-field label="Role" name="role_id" required
                                     :options="$roles->pluck('name', 'id')">
                        <option value="" disabled @selected(! old('role_id'))>Select a role…</option>
                    </x-select-field>

                    <div class="pt-1">
                        <x-button variant="gold" tag="button" type="submit" icon="check" class="w-full">
                            Create &amp; generate credentials
                        </x-button>
                    </div>
                </form>
            </x-modal>
        </x-slot:actions>
    </x-page-header>


    <x-data-table
        :paginator="$members"
        label="team members"
        :searchable="false"
        empty-title="No team members yet"
        empty-description="Add a team member and assign them a role to get started.">

        <x-slot:head>
            <x-th>Name</x-th>
            <x-th hide="lg">Role</x-th>
            <x-th hide="xl">Created</x-th>
            <x-th>Status</x-th>
            <x-th align="right">Actions</x-th>
        </x-slot:head>

        @foreach($members as $member)
            {{-- The row just created is tinted so it's findable in a long, name-sorted list. --}}
            <tr @class([
                    'hover:bg-canvas transition-colors',
                    'bg-success-soft' => session('created_id') === $member->id,
                ])>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-avatar :name="$member->name" size="md" />
                        <div class="min-w-0">
                            <p class="text-[13px] font-medium text-ink truncate">{{ $member->name }}</p>
                            <p class="text-[11.5px] text-ink-3 truncate">{{ $member->email }}</p>
                        </div>
                    </div>
                </td>

                <td class="px-4 py-3 hidden lg:table-cell">
                    @if($member->isSuperAdmin())
                        <x-badge tone="primary" size="sm">Super Admin</x-badge>
                    @else
                        <span class="text-[12.5px] text-ink-2">{{ $member->adminRole?->name ?? '—' }}</span>
                    @endif
                </td>

                <td class="px-4 py-3 text-[12.5px] text-ink-3 nums hidden xl:table-cell">
                    {{ $member->created_at->format('d M Y') }}
                </td>

                <td class="px-4 py-3">
                    <x-badge :tone="$member->status === 'active' ? 'success' : 'neutral'" size="sm" dot>
                        {{ ucfirst($member->status) }}
                    </x-badge>
                </td>

                <td class="px-4 py-3 text-right">
                    <x-dropdown>
                        <x-slot:trigger>
                            <button type="button" aria-label="Actions for {{ $member->name }}"
                                    class="text-ink-3 hover:text-ink hover:bg-canvas rounded-md p-1.5 transition-colors">
                                <x-icon name="dots" class="w-4 h-4" />
                            </button>
                        </x-slot:trigger>

                        {{-- Payloads are built with Js::from + {{ }} rather than @js() —
                             Blade does not compile directives inside a component's
                             attribute string. --}}
                        @php
                            $editPayload = \Illuminate\Support\Js::from([
                                'name' => $member->name,
                                'email' => $member->email,
                                'roleId' => (string) $member->role_id,
                                'status' => $member->status,
                                'action' => route('admin.team.update', $member),
                            ]);
                            $resetPayload = \Illuminate\Support\Js::from([
                                'name' => $member->name,
                                'action' => route('admin.team.password', $member),
                            ]);
                            $sharePayload = \Illuminate\Support\Js::from([
                                'name' => $member->name,
                                'email' => $member->email,
                                // Lets the share dialog offer "set a password" in one step.
                                'resetAction' => route('admin.team.password', $member),
                            ]);
                            $deletePayload = \Illuminate\Support\Js::from([
                                'title' => 'Remove this team member?',
                                'message' => "{$member->name} loses access immediately and the account is deleted. "
                                    . 'This cannot be undone.',
                                'confirmLabel' => 'Delete account',
                                'tone' => 'danger',
                            ]);
                        @endphp

                        <x-dropdown-item icon="cog" tag="button" type="button"
                                         x-on:click="$dispatch('edit-member', {{ $editPayload }}); close()">
                            Edit details
                        </x-dropdown-item>

                        {{-- Available on every row, Super Admin included: sharing a sign-in
                             link changes nothing about the account. --}}
                        <x-dropdown-item icon="external" tag="button" type="button"
                                         x-on:click="$dispatch('share-credentials', {{ $sharePayload }}); close()">
                            Share sign-in details
                        </x-dropdown-item>

                        {{-- Stored passwords are hashed, so the existing one can never be
                             re-shown — this sets a new one the admin types in. --}}
                        <x-dropdown-item icon="lock" tag="button" type="button"
                                         x-on:click="$dispatch('reset-password', {{ $resetPayload }}); close()">
                            Reset password
                        </x-dropdown-item>

                        @unless($member->isSuperAdmin())
                            <div class="my-1 border-t border-line-soft"></div>

                            @foreach($roles as $role)
                                <form method="POST" action="{{ route('admin.team.update', $member) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="role_id" value="{{ $role->id }}">
                                    <input type="hidden" name="status" value="{{ $member->status }}">
                                    <x-dropdown-item icon="shield" tag="button" type="submit">
                                        Set role: {{ $role->name }}
                                    </x-dropdown-item>
                                </form>
                            @endforeach

                            <div class="my-1 border-t border-line-soft"></div>

                            <form method="POST" action="{{ route('admin.team.update', $member) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="role_id" value="{{ $member->role_id }}">
                                <input type="hidden" name="status" value="{{ $member->status === 'active' ? 'paused' : 'active' }}">
                                <x-dropdown-item :icon="$member->status === 'active' ? 'x' : 'check'"
                                                 :tone="$member->status === 'active' ? 'danger' : 'default'"
                                                 tag="button" type="submit">
                                    {{ $member->status === 'active' ? 'Pause account' : 'Reactivate account' }}
                                </x-dropdown-item>
                            </form>
                        @endunless

                        <div class="my-1 border-t border-line-soft"></div>

                        <form method="POST" action="{{ route('admin.team.destroy', $member) }}"
                              x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $deletePayload }}, form: $el }); close()">
                            @csrf @method('DELETE')
                            <x-dropdown-item icon="x" tone="danger" tag="button" type="submit">
                                Delete account
                            </x-dropdown-item>
                        </form>
                    </x-dropdown>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    @php
        // A failed edit redirects back here, so the dialog reopens with what was typed
        // instead of silently discarding it. `_form` marks it as the edit form and
        // `_action` carries the row's endpoint, which is lost on the redirect.
        $editReopen = $errors->any() && old('_form') === 'edit' ? [
            'name' => old('name'),
            'email' => old('email'),
            'roleId' => (string) old('role_id'),
            'status' => old('status'),
            'action' => old('_action'),
        ] : null;
    @endphp

    {{-- ============================ Edit member ============================ --}}
    <div x-data="editMemberDialog(@js($editReopen))"
         x-on:edit-member.window="request($event.detail)"
         x-show="open" x-cloak
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-[55] flex items-start justify-center px-4 py-10 overflow-y-auto"
         role="dialog" aria-modal="true" aria-labelledby="edit-title">

        <div x-show="open" x-transition.opacity @click="open = false" class="fixed inset-0 bg-scrim"></div>

        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-3 scale-[0.98]"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             class="relative bg-panel rounded-2xl w-full max-w-lg shadow-modal my-auto">

            <header class="px-6 py-4 border-b border-line flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3 id="edit-title" class="text-[15px] font-semibold text-ink tracking-[-0.01em]">Edit team member</h3>
                    <p class="text-[12.5px] text-ink-3 mt-0.5">Changes apply the next time they load a page.</p>
                </div>
                <button type="button" @click="open = false" aria-label="Close dialog"
                        class="text-ink-3 hover:text-ink hover:bg-canvas rounded-lg p-1.5 -m-1 transition-colors shrink-0">
                    <x-icon name="x" class="w-4.5 h-4.5" />
                </button>
            </header>

            <form method="POST" :action="action" class="px-6 py-5 space-y-4">
                @csrf @method('PATCH')
                <input type="hidden" name="_form" value="edit">
                <input type="hidden" name="_action" :value="action">

                <div>
                    <label for="edit-name" class="flex items-center gap-1 text-[12.5px] font-medium text-ink mb-1.5">
                        Full name <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <input id="edit-name" name="name" x-model="form.name" required minlength="2" maxlength="255"
                           @class(['w-full h-10 px-3.5 rounded-lg bg-panel border text-[13.5px] text-ink focus:outline-none focus:ring-[3px] transition-[border-color,box-shadow]',
                                   'border-danger focus:border-danger focus:ring-danger-ring' => $errors->has('name'),
                                   'border-line focus:border-primary focus:ring-primary-ring' => ! $errors->has('name')])>
                    @error('name')<p class="text-[11.5px] text-danger mt-1.5">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="edit-email" class="flex items-center gap-1 text-[12.5px] font-medium text-ink mb-1.5">
                        Email <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <input id="edit-email" name="email" type="email" x-model="form.email" required maxlength="255"
                           @class(['w-full h-10 px-3.5 rounded-lg bg-panel border text-[13.5px] text-ink focus:outline-none focus:ring-[3px] transition-[border-color,box-shadow]',
                                   'border-danger focus:border-danger focus:ring-danger-ring' => $errors->has('email'),
                                   'border-line focus:border-primary focus:ring-primary-ring' => ! $errors->has('email')])>
                    @error('email')<p class="text-[11.5px] text-danger mt-1.5">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="edit-role" class="flex items-center gap-1 text-[12.5px] font-medium text-ink mb-1.5">
                            Role <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select id="edit-role" name="role_id" x-model="form.roleId" required
                                class="w-full h-10 pl-3.5 pr-9 rounded-lg bg-panel border border-line text-[13.5px] text-ink
                                       focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="edit-status" class="flex items-center gap-1 text-[12.5px] font-medium text-ink mb-1.5">
                            Status <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select id="edit-status" name="status" x-model="form.status" required
                                class="w-full h-10 pl-3.5 pr-9 rounded-lg bg-panel border border-line text-[13.5px] text-ink
                                       focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
                            <option value="active">Active</option>
                            <option value="paused">Paused</option>
                        </select>
                    </div>
                </div>

                <div class="pt-1 flex items-center justify-end gap-2.5">
                    <button type="button" @click="open = false"
                            class="h-9 px-4 rounded-lg text-[13px] font-medium text-ink-2 hover:text-ink hover:bg-line-soft transition-colors">
                        Cancel
                    </button>
                    <x-button variant="gold" tag="button" type="submit" icon="check">Save changes</x-button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('editMemberDialog', (reopen) => ({
                    open: !! reopen,
                    action: reopen?.action ?? '',
                    form: {
                        name: reopen?.name ?? '',
                        email: reopen?.email ?? '',
                        roleId: reopen?.roleId ?? '',
                        status: reopen?.status ?? 'active',
                    },

                    request(detail) {
                        this.action = detail.action;
                        this.form = {
                            name: detail.name,
                            email: detail.email,
                            roleId: detail.roleId,
                            status: detail.status,
                        };
                        this.open = true;
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.admin>
