{{--
    The Team roster table — its own pagination and per-page control refresh this
    whole block in place instead of a full page reload. `data-ajax-panel` is what
    marks it for app.js's generic panel mechanism (see the note there): any link or
    GET form inside this element is fetched and swapped back in, rather than
    navigating. The row actions inside <x-data-table> (edit, delete, role change,
    pause) are deliberately NOT part of that contract — they are unrelated to why the
    screen used to reload (that was pagination/per-page alone) and keep working
    exactly as before, a real POST/redirect/reload.

    This exact file is what both TeamController::index()'s full-page branch and its
    ajax() fragment branch render — never two versions of the same table to keep in
    sync by hand.
--}}
<div id="team-table" data-ajax-panel>
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
                            <x-dropdown-item icon="trash" tone="danger" tag="button" type="submit">
                                Delete account
                            </x-dropdown-item>
                        </form>
                    </x-dropdown>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</div>
