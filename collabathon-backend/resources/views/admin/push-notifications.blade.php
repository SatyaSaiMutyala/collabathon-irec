<x-layouts.admin active="push-notifications" title="Push Notifications" section="Configure">

    <x-page-header
        title="Push Notifications"
        subtitle="Send a manual push to channel partners, developers, or both." />

    @unless($firebaseConfigured)
        <div class="flex items-start gap-3 bg-warning-soft ring-1 ring-inset ring-warning-ring px-4 py-3 mb-5">
            <x-icon name="lock" class="w-4 h-4 text-warning shrink-0 mt-0.5" />
            <div class="min-w-0">
                <p class="text-[12.5px] font-medium text-ink">No Firebase service account saved</p>
                <p class="text-[11.5px] text-ink-2 mt-0.5 leading-relaxed">
                    Sending below will run without error, but reach nobody — Firebase has nowhere to deliver to yet.
                </p>
            </div>
            @can('manage-team')
                <a href="{{ route('admin.settings', ['tab' => 'access']) }}"
                   class="ml-auto shrink-0 text-[12px] font-medium text-primary-dark hover:underline whitespace-nowrap">
                    Set it up
                </a>
            @endcan
        </div>
    @endunless

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">
        <x-panel title="Send a push notification"
                 subtitle="Reaches everyone in the audience who currently has the app installed"
                 padded class="xl:col-span-2">
            <form method="POST" action="{{ route('admin.push-notifications.store') }}"
                  enctype="multipart/form-data" class="space-y-3"
                  x-on:submit.prevent="$dispatch('confirm-request', {
                      title: 'Send this to every device?',
                      message: 'Push notifications cannot be recalled once sent.',
                      confirmLabel: 'Send',
                      form: $el,
                  })">
                @csrf

                <x-select-field label="Audience" name="audience"
                                :options="['brokers' => 'Channel partners', 'developers' => 'Developers', 'everyone' => 'Everyone']"
                                :value="old('audience', 'everyone')" />

                <x-field label="Title" name="title" :value="old('title')"
                         placeholder="New projects are live"
                         hint="Under 60 characters — Android truncates past that." />

                <x-field label="Message" name="body" type="textarea" rows="5" :value="old('body')"
                         placeholder="Six new Dubai listings were added this week."
                         hint="Shown in full on the notification's detail screen — the list itself only shows the first couple of lines." />

                {{-- FCM fetches this from Google's servers, not from the device, so the
                     URL has to be publicly reachable — a LAN APP_URL sends fine and simply
                     arrives without the picture. --}}
                <x-file-field label="Image (optional)" name="image" accept="image/*"
                              hint="PNG or JPG under 2 MB. Shown as a large picture on the device." />

                <div class="flex items-center justify-between gap-3 pt-1">
                    <p class="text-[11.5px] text-ink-3">
                        Lifecycle notifications — approvals, requests, decisions — send
                        automatically and are not affected by this.
                    </p>
                    <x-button variant="gold" size="sm" tag="button" type="submit" icon="bell">
                        Send
                    </x-button>
                </div>
            </form>
        </x-panel>

        <div class="space-y-4 self-start">
            <x-panel title="Who this reaches right now" padded>
                <dl class="divide-y divide-line-soft">
                    <div class="py-2.5 flex items-center justify-between gap-3">
                        <dt class="text-[12.5px] text-ink-2">Channel partners</dt>
                        <dd class="text-[13px] font-medium text-ink nums">{{ $reachable['brokers'] }}</dd>
                    </div>
                    <div class="py-2.5 flex items-center justify-between gap-3">
                        <dt class="text-[12.5px] text-ink-2">Developers</dt>
                        <dd class="text-[13px] font-medium text-ink nums">{{ $reachable['developers'] }}</dd>
                    </div>
                    <div class="py-2.5 flex items-center justify-between gap-3">
                        <dt class="text-[12.5px] font-medium text-ink">Everyone</dt>
                        <dd class="text-[13px] font-semibold text-ink nums">{{ $reachable['brokers'] + $reachable['developers'] }}</dd>
                    </div>
                </dl>
                <p class="text-[11px] text-ink-3 mt-3 pt-3 border-t border-line-soft leading-relaxed">
                    Active accounts with the app installed and a device registered — not everyone with an
                    account. Nobody here yet usually means Firebase isn't configured, or nobody has opened
                    the app since it was.
                </p>
            </x-panel>

            <x-panel title="How this works" padded>
                <ul class="space-y-2.5 text-[12.5px] text-ink-2 leading-relaxed">
                    <li class="flex gap-2">
                        <span class="text-primary">&bull;</span>
                        <span>Sends run inline on this request — a broadcast over 500 devices needs a queue
                            worker before it finishes, and is refused with that explanation instead.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-primary">&bull;</span>
                        <span>Only <strong class="text-ink">active</strong> accounts are ever notified — a
                            pending or rejected registration never receives one of these.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-primary">&bull;</span>
                        <span>This is separate from lifecycle notifications (approvals, lead updates) — those
                            fire automatically from the events themselves and have no UI at all.</span>
                    </li>
                </ul>
            </x-panel>
        </div>
    </div>

    {{-- ============================== History ==============================
         Only manual sends appear here. Lifecycle pushes are not recorded: each one
         already has a domain record behind it, and the app derives its own in-app list
         from those. See the announcements migration. --}}
    <x-panel title="Sent notifications" flush class="mt-4">
        <x-slot:actions>
            <span class="text-[11.5px] text-ink-3 nums">{{ $announcements->total() }} total</span>
        </x-slot:actions>

        <div class="overflow-x-auto scrollbar-slim">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line-soft">
                        <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3">Notification</th>
                        <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3">Audience</th>
                        <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3">Delivered</th>
                        <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3">Sent by</th>
                        <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3">When</th>
                        <th scope="col" class="px-4 py-2.5"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    @forelse($announcements as $announcement)
                        <tr class="hover:bg-canvas transition-colors align-top">
                            <td class="px-4 py-3">
                                <div class="flex items-start gap-2.5 min-w-0">
                                    @if($announcement->imageUrl())
                                        <img src="{{ $announcement->imageUrl() }}" alt=""
                                             class="w-10 h-10 rounded-lg object-cover border border-line-soft shrink-0">
                                    @endif
                                    <div class="min-w-0">
                                        <p class="text-[13px] font-medium text-ink">{{ $announcement->title }}</p>
                                        <p class="text-[12px] text-ink-2 mt-0.5 max-w-[46ch]">{{ $announcement->body }}</p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <x-badge tone="neutral" size="sm">{{ $announcement->audienceLabel() }}</x-badge>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="text-[13px] text-ink nums">{{ number_format($announcement->sent_count) }}</span>
                                <span class="text-[11.5px] text-ink-3"> / {{ number_format($announcement->recipients) }}</span>
                                @if($announcement->failed_count)
                                    <p class="text-[11px] text-danger mt-0.5 nums">
                                        {{ number_format($announcement->failed_count) }} failed
                                    </p>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-[12.5px] text-ink-2 whitespace-nowrap">
                                {{ $announcement->sender?->name ?? '—' }}
                            </td>

                            <td class="px-4 py-3 text-[12.5px] text-ink-3 nums whitespace-nowrap">
                                {{ $announcement->created_at->format('d M Y, H:i') }}
                            </td>

                            <td class="px-4 py-3 text-right">
                                @php
                                    // Says plainly what deleting can and cannot undo: the push
                                    // has already been delivered and nothing here recalls it.
                                    $deletePayload = \Illuminate\Support\Js::from([
                                        'title' => 'Delete this notification?',
                                        'message' => "\"{$announcement->title}\" will be removed from this "
                                            . 'history and from the in-app Notifications screen. Devices that '
                                            . 'already received the push keep it.',
                                        'confirmLabel' => 'Delete notification',
                                        'tone' => 'danger',
                                    ]);
                                @endphp

                                <form method="POST" action="{{ route('admin.push-notifications.destroy', $announcement) }}"
                                      x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $deletePayload }}, form: $el })">
                                    @csrf @method('DELETE')
                                    <x-button variant="danger-ghost" size="sm" icon="trash" tag="button" type="submit"
                                              aria-label="Delete {{ $announcement->title }}" />
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-[12.5px] text-ink-3 text-center">
                                Nothing sent yet. Manual pushes you send appear here.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($announcements->hasPages())
            <div class="px-4 py-3 border-t border-line-soft">
                <x-pagination :paginator="$announcements" label="notifications" />
            </div>
        @endif
    </x-panel>
</x-layouts.admin>
