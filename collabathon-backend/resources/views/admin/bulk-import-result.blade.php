{{-- Shared by both DeveloperController::bulkImport() and PropertyController::bulkImport()
     — `type` ('developers'|'properties') is the only thing that varies between them, so
     this stays one view instead of two near-identical copies. --}}
<x-layouts.admin :active="$type" title="Bulk upload result" section="Manage">

    <a href="{{ route('admin.' . $type) }}"
       class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2 hover:text-ink transition-colors mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" />
        Back to {{ $type }}
    </a>

    <x-page-header title="Bulk upload result"
                   subtitle="Every row was validated and saved on its own — one bad row never blocks the rest of the sheet." />

    <div class="grid grid-cols-2 sm:grid-cols-2 gap-3.5 mb-5">
        <x-stat-card icon="check" label="Created" :value="$created" />
        <x-stat-card icon="x" label="Failed" :value="$failed" />
    </div>

    <x-panel title="Rows" :subtitle="count($results) . ' processed'" flush>
        <dl class="divide-y divide-line-soft">
            @foreach($results as $row)
                <div class="px-5 py-3 flex items-start gap-4">
                    <dt class="text-[11px] text-ink-3 w-10 shrink-0 pt-0.5 nums">#{{ $row['row'] }}</dt>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[13px] text-ink font-medium">
                                {{ $row['company_name'] ?? $row['name'] ?? '—' }}
                            </span>
                            <x-badge :tone="$row['status'] === 'created' ? 'success' : 'danger'" size="sm">
                                {{ ucfirst($row['status']) }}
                            </x-badge>
                        </div>

                        @if($row['status'] === 'created')
                            <p class="text-[12px] text-ink-2 mt-1">
                                @if(isset($row['email']))
                                    {{ $row['email'] }}
                                    @if($row['password'])
                                        · <span class="font-mono">{{ $row['password'] }}</span>
                                        <span class="text-ink-3">(temporary password — shown once)</span>
                                    @endif
                                @endif
                            </p>
                            @if(isset($row['property_id']))
                                <a href="{{ route('admin.properties.show', $row['property_id']) }}"
                                   class="inline-flex items-center gap-1 text-[12px] text-primary hover:underline mt-1">
                                    Open &amp; add images <x-icon name="external" class="w-3 h-3" />
                                </a>
                            @endif
                        @else
                            <p class="text-[12px] text-danger mt-1">{{ $row['error'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </dl>
    </x-panel>
</x-layouts.admin>
