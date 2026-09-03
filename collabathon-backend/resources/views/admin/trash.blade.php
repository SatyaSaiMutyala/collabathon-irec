<x-layouts.admin active="trash" title="Trash" section="Configure">

    <x-page-header
        title="Trash"
        :subtitle="$rows->count() . ' item' . ($rows->count() === 1 ? '' : 's') . ' — hidden from the platform but not gone. Restore them, or delete them permanently.'" />

    {{-- ---------------------------- type filter chips ---------------------------- --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <a href="{{ route('admin.trash') }}{{ $search ? '?search=' . urlencode($search) : '' }}"
           @class([
               'inline-flex items-center gap-1.5 h-8 px-3 rounded-full text-[12.5px] font-medium transition-colors',
               'bg-nav text-white' => ! $activeType,
               'bg-panel border border-line text-ink-2 hover:border-ink-3' => $activeType,
           ])>
            All
            <span @class([
                'nums text-[11px]',
                'text-white/70' => ! $activeType,
                'text-ink-3' => $activeType,
            ])>{{ $counts->sum() }}</span>
        </a>
        @foreach($types as $key => [$icon, $label])
            @php $count = $counts->get($key, 0); @endphp
            @if($count > 0)
                <a href="{{ route('admin.trash', array_filter(['type' => $key, 'search' => $search])) }}"
                   @class([
                       'inline-flex items-center gap-1.5 h-8 px-3 rounded-full text-[12.5px] font-medium transition-colors',
                       'bg-nav text-white' => $activeType === $key,
                       'bg-panel border border-line text-ink-2 hover:border-ink-3' => $activeType !== $key,
                   ])>
                    <x-icon :name="$icon" class="w-3.5 h-3.5" />
                    {{ Str::plural($label) }}
                    <span @class([
                        'nums text-[11px]',
                        'text-white/70' => $activeType === $key,
                        'text-ink-3' => $activeType !== $key,
                    ])>{{ $count }}</span>
                </a>
            @endif
        @endforeach
    </div>

    <div class="bg-panel border border-line rounded-2xl shadow-card flex flex-col min-w-0">
        {{-- ---------------------------- search ---------------------------- --}}
        <div class="px-4 py-3 border-b border-line-soft">
            <form method="GET" action="{{ route('admin.trash') }}" class="max-w-sm">
                @if($activeType)
                    <input type="hidden" name="type" value="{{ $activeType }}">
                @endif
                <label class="relative block">
                    <span class="sr-only">Search Trash</span>
                    <x-icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search Trash…"
                           x-on:input="if (! $event.target.value) $event.target.form.requestSubmit()"
                           class="w-full h-9 pl-9 pr-3 rounded-lg bg-canvas border border-transparent shadow-card text-[13px] text-ink
                                  placeholder:text-ink-3 hover:border-line focus:bg-panel focus:border-primary-ring
                                  focus:outline-none transition-colors">
                </label>
            </form>
        </div>

        @if($rows->isEmpty())
            <x-empty-state icon="trash" title="Trash is empty"
                           :description="$search || $activeType
                               ? 'Nothing here matches — try clearing the search or filter.'
                               : 'Anything you delete across the admin panel — developers, listings, team members, channel partners and more — shows up here first.'">
                @if($search || $activeType)
                    <x-slot:action>
                        <x-button variant="outline" size="sm" tag="a" href="{{ route('admin.trash') }}">Clear filters</x-button>
                    </x-slot:action>
                @endif
            </x-empty-state>
        @else
            <div class="overflow-x-auto scrollbar-slim">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-line-soft">
                            <x-th>Item</x-th>
                            <x-th hide="md">Type</x-th>
                            <x-th hide="lg">Deleted</x-th>
                            <x-th align="right">Actions</x-th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft">
                        @foreach($rows as $row)
                            <tr class="hover:bg-canvas transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <x-avatar :name="$row['name']" :src="$row['avatar']" size="sm" />
                                        <div class="min-w-0">
                                            <p class="text-[13px] font-medium text-ink truncate">{{ $row['name'] }}</p>
                                            <p class="text-[11.5px] text-ink-3 truncate">{{ $row['subtitle'] }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-3 hidden md:table-cell">
                                    <x-badge tone="neutral" size="sm">
                                        <x-icon :name="$row['icon']" class="w-3 h-3" />
                                        {{ $row['type_label'] }}
                                    </x-badge>
                                </td>

                                <td class="px-4 py-3 hidden lg:table-cell">
                                    <span class="text-[12px] text-ink-3 whitespace-nowrap">{{ $row['deleted_at']->diffForHumans() }}</span>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" action="{{ $row['restore_route'] }}">
                                            @csrf
                                            <x-button variant="outline" size="sm" icon="arrow-up" tag="button" type="submit">
                                                Restore
                                            </x-button>
                                        </form>

                                        @php
                                            $forceDeletePayload = \Illuminate\Support\Js::from([
                                                'title' => 'Permanently delete this ' . strtolower($row['type_label']) . '?',
                                                'message' => $row['force_delete_message'],
                                                'confirmLabel' => 'Delete permanently',
                                                'tone' => 'danger',
                                            ]);
                                        @endphp
                                        <form method="POST" action="{{ $row['force_delete_route'] }}"
                                              x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $forceDeletePayload }}, form: $el })">
                                            @csrf @method('DELETE')
                                            <x-button variant="danger-ghost" size="sm" icon="trash" tag="button" type="submit">
                                                Delete permanently
                                            </x-button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.admin>
