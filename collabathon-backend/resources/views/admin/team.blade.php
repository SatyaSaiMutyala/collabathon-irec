<x-layouts.admin active="team" title="Team" section="Administration">

    <x-page-header
        title="Team"
        subtitle="Admin-side staff accounts. Each is assigned a role that controls what they can see and do.">
        <x-slot:actions>
            <x-modal title="Create team member"
                     subtitle="A login account is created and a temporary password generated on save."
                     width="max-w-lg"
                     :open="$errors->any()">
                <x-slot:trigger>
                    <x-button variant="gold" icon="plus">Add team member</x-button>
                </x-slot:trigger>

                <form method="POST" action="{{ route('admin.team.store') }}" class="space-y-4">
                    @csrf
                    <x-field label="Full name" name="name" placeholder="Full name" required />
                    <x-field label="Email" name="email" type="email" placeholder="name@company.ae" icon="mail" required
                             hint="This becomes their login." />
                    <x-select-field label="Role" name="role_id" required
                                     :options="$roles->pluck('name', 'id')" />
                    <div class="pt-1">
                        <x-button variant="gold" tag="button" type="submit" icon="check" class="w-full">
                            Create &amp; generate credentials
                        </x-button>
                    </div>
                </form>
            </x-modal>
        </x-slot:actions>
    </x-page-header>

    <x-flash />

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
            <x-th align="right"><span class="sr-only">Actions</span></x-th>
        </x-slot:head>

        @foreach($members as $member)
            <tr class="hover:bg-canvas transition-colors">
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
                    @unless($member->isSuperAdmin())
                        <x-dropdown>
                            <x-slot:trigger>
                                <button type="button" aria-label="Actions for {{ $member->name }}"
                                        class="text-ink-3 hover:text-ink hover:bg-canvas rounded-md p-1.5 transition-colors">
                                    <x-icon name="dots" class="w-4 h-4" />
                                </button>
                            </x-slot:trigger>

                            @foreach($roles->where('is_system', false) as $role)
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
                        </x-dropdown>
                    @endunless
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
