<x-layouts.admin active="roles" title="Roles" section="Administration">

    <x-page-header
        title="Roles"
        subtitle="Define what each admin-side role can view, edit, and delete across the panel.">
        <x-slot:actions>
            <x-modal title="Create role"
                     subtitle="Choose a name and set what this role can do in each module."
                     width="max-w-2xl"
                     :open="$errors->any()">
                <x-slot:trigger>
                    <x-button variant="gold" icon="plus">Add role</x-button>
                </x-slot:trigger>

                <form method="POST" action="{{ route('admin.roles.store') }}" class="space-y-4">
                    @csrf
                    <x-field label="Role name" name="name" placeholder="e.g. Manager" required />
                    <x-permission-matrix :modules="$modules" />
                    <div class="pt-1">
                        <x-button variant="gold" tag="button" type="submit" icon="check" class="w-full">
                            Create role
                        </x-button>
                    </div>
                </form>
            </x-modal>
        </x-slot:actions>
    </x-page-header>


    <x-panel flush>
        <div class="divide-y divide-line-soft">
            @foreach($roles as $role)
                <div class="flex items-center justify-between gap-4 px-5 py-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-[13px] font-medium text-ink">{{ $role->name }}</p>
                            @if($role->is_system)
                                <x-badge tone="primary" size="sm">System</x-badge>
                            @endif
                        </div>
                        <p class="text-[11.5px] text-ink-3 mt-0.5">
                            {{ $role->users_count }} {{ $role->users_count === 1 ? 'member' : 'members' }}
                        </p>
                    </div>

                    @unless($role->is_system)
                        <div class="flex items-center gap-2 shrink-0">
                            <x-modal title="Edit role" subtitle="Update permissions for {{ $role->name }}." width="max-w-2xl">
                                <x-slot:trigger>
                                    <x-button variant="outline" size="sm" icon="cog">Edit</x-button>
                                </x-slot:trigger>

                                <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-4">
                                    @csrf @method('PATCH')
                                    <x-field label="Role name" name="name" value="{{ $role->name }}" required />
                                    <x-permission-matrix :modules="$modules" :permissions="$role->permissions" />
                                    <div class="pt-1">
                                        <x-button variant="gold" tag="button" type="submit" icon="check" class="w-full">
                                            Save changes
                                        </x-button>
                                    </div>
                                </form>
                            </x-modal>

                            @php
                                // Js::from + {{ }} rather than @js() — Blade does not compile
                                // directives inside a component's attribute string (same
                                // reasoning as developers.blade.php's own delete payload).
                                $deletePayload = \Illuminate\Support\Js::from([
                                    'title' => 'Delete this role?',
                                    'message' => $role->users_count > 0
                                        ? 'This cannot be undone, and ' . $role->users_count . ' '
                                            . ($role->users_count === 1 ? 'member' : 'members')
                                            . ' will lose the permissions it grants.'
                                        : 'This cannot be undone.',
                                    'confirmLabel' => 'Delete',
                                    'tone' => 'danger',
                                ]);
                            @endphp
                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}"
                                  x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $deletePayload }}, form: $el })">
                                @csrf @method('DELETE')
                                <x-button variant="danger-ghost" size="sm" tag="button" type="submit" icon="x">
                                    Delete
                                </x-button>
                            </form>
                        </div>
                    @endunless
                </div>
            @endforeach
        </div>
    </x-panel>
</x-layouts.admin>
