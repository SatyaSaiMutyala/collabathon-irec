{{--
    Same shape as approvals/partials/table.blade.php — its own pagination/search/step
    filter refresh this block in place via the shared `data-ajax-panel` mechanism, and
    this exact file is what both branches of ApprovalController::drafts() render.

    No Approve/Reject here: a draft hasn't reached a real step-3 submit, so there is
    nothing yet to decide — the row's only action is opening the same review page
    Pending/Decided already use, which works for a broker at any status.
--}}
<div id="approvals-drafts-table" data-ajax-panel>
    <x-data-table
        :paginator="$drafts"
        label="drafts"
        search-placeholder="Search by name, email or mobile…"
        empty-icon="clock"
        empty-title="No drafts in progress"
        empty-description="Everyone who has started registering has either finished or been decided on.">

        <x-slot:filters>
            <x-filter-select name="step"
                             :options="['1' => 'Step 1', '2' => 'Step 2', '3' => 'Step 3']"
                             placeholder="Any step" />
        </x-slot:filters>

        <x-slot:head>
            <x-th>Channel Partner</x-th>
            <x-th hide="lg">Contact</x-th>
            <x-th>Progress</x-th>
            <x-th hide="md">Last active</x-th>
            <x-th align="right">Started</x-th>
            <x-th align="right"><span class="sr-only">Actions</span></x-th>
        </x-slot:head>

        @foreach($drafts as $broker)
            @php
                $profile = $broker->brokerProfile;
                // No profile row at all yet means the very first instant after sign-up,
                // before even a single save — reads the same as step 1.
                $step = $profile?->registration_step ?? 1;
                $stepTone = match ($step) {
                    3 => 'warning', // did all three steps' worth of work but never hit the real Submit
                    2 => 'primary',
                    default => 'neutral',
                };
            @endphp
            <tr class="hover:bg-canvas transition-colors cursor-pointer"
                x-on:click="if (! $event.target.closest('[data-row-actions]')) window.location = @js(route('admin.approvals.show', [$broker, 'from' => 'drafts']))">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-avatar :name="$broker->name" :src="$profile?->photo_path" size="md" />
                        <div class="min-w-0">
                            <a href="{{ route('admin.approvals.show', [$broker, 'from' => 'drafts']) }}"
                               class="text-[13px] font-medium text-ink hover:underline truncate block">{{ $broker->name }}</a>
                            <p class="text-[11.5px] text-ink-3 truncate">{{ $profile?->company_name ?: $broker->email }}</p>
                        </div>
                    </div>
                </td>

                <td class="px-4 py-3 hidden lg:table-cell">
                    <p class="text-[12.5px] text-ink-2 nums">{{ $broker->mobile }}</p>
                    <p class="text-[11.5px] text-ink-3 truncate">{{ $broker->email }}</p>
                </td>

                <td class="px-4 py-3">
                    <x-badge :tone="$stepTone" size="sm">Step {{ $step }} of 3</x-badge>
                </td>

                <td class="px-4 py-3 hidden md:table-cell">
                    <span class="text-[12.5px] text-ink-2">{{ $broker->updated_at->diffForHumans() }}</span>
                </td>

                <td class="px-4 py-3 text-right">
                    <span class="text-[12.5px] text-ink-3 nums">{{ $broker->created_at->format('d M Y') }}</span>
                </td>

                {{-- data-row-actions stops the row's click-through firing in here. --}}
                <td class="px-4 py-3 text-right" data-row-actions>
                    @php
                        // A draft never reached the queue, so there is nothing to reject —
                        // deleting is the only way to clear an abandoned sign-up.
                        $deletePayload = \Illuminate\Support\Js::from([
                            'title' => 'Delete this draft?',
                            'message' => "{$broker->name}'s unfinished registration and any "
                                . 'documents already uploaded will be permanently deleted.',
                            'confirmLabel' => 'Delete draft',
                            'tone' => 'danger',
                        ]);
                    @endphp

                    <form method="POST" action="{{ route('admin.approvals.destroy', $broker) }}"
                          x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $deletePayload }}, form: $el })">
                        @csrf @method('DELETE')
                        <x-button variant="danger-ghost" size="sm" icon="x" tag="button" type="submit"
                                  aria-label="Delete {{ $broker->name }}'s draft" />
                    </form>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</div>
