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
            <form method="POST" action="{{ route('admin.push-notifications.store') }}" class="space-y-3"
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

                <x-field label="Message" name="body" type="textarea" rows="2" :value="old('body')"
                         placeholder="Six new Dubai listings were added this week."
                         hint="Under 180 characters so it reads without expanding." />

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
</x-layouts.admin>
