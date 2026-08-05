@php
    $isAccept = $action === 'accept';
    $detail = $property->detail;
@endphp
<x-layouts.guest :title="($isAccept ? 'Accept' : 'Decline') . ' — ' . $property->name">
    <div class="min-h-screen flex items-center justify-center px-5 py-12 bg-canvas">
        <div class="w-full max-w-[480px]">

            <div class="flex items-center gap-2.5 mb-8 justify-center">
                <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center">
                    <span class="text-white font-bold text-[12px]">iR</span>
                </div>
                <span class="text-ink font-semibold text-[14.5px]">{{ config('app.name') }}</span>
            </div>

            <div class="bg-panel border border-line rounded-2xl shadow-card overflow-hidden">
                <div class="px-6 pt-6 pb-5 border-b border-line">
                    <p class="text-[11px] font-semibold tracking-[0.08em] uppercase {{ $isAccept ? 'text-primary-dark' : 'text-danger' }}">
                        {{ $isAccept ? 'Accepting this project' : 'Declining this project' }}
                    </p>
                    <h1 class="text-[19px] font-semibold text-ink tracking-[-0.01em] mt-1">
                        {{ $property->name }}
                    </h1>
                    <p class="text-[12.5px] text-ink-2 mt-1">
                        {{ $property->developer?->company_name }} &middot; {{ $property->locality }}, {{ $property->city }}
                    </p>
                </div>

                <div class="px-6 py-5">
                    @if($alreadyRespondedAs)
                        <div class="flex items-start gap-2.5 rounded-lg bg-canvas ring-1 ring-inset ring-line px-3.5 py-3 mb-5">
                            <x-icon name="clock" class="w-4 h-4 text-ink-3 shrink-0 mt-0.5" />
                            <p class="text-[12.5px] text-ink-2 leading-relaxed">
                                You already marked this project as
                                <strong class="text-ink">{{ ucfirst($alreadyRespondedAs) }}</strong>
                                on {{ $property->developer_responded_at?->format('d M Y') }}.
                                Submitting below will update it.
                            </p>
                        </div>
                    @endif

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 mb-5">
                        <div>
                            <dt class="text-[11px] text-ink-3">Price range</dt>
                            <dd class="text-[13px] font-medium text-ink mt-0.5">
                                {{ $property->currency }} {{ number_format($property->price_min) }} – {{ number_format($property->price_max) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-ink-3">Unit types</dt>
                            <dd class="text-[13px] font-medium text-ink mt-0.5">{{ $property->unitTypes->count() }} configurations</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-ink-3">CP commission</dt>
                            <dd class="text-[13px] font-medium text-ink mt-0.5">{{ $detail?->cp_commission_percent ?? '—' }}%</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-ink-3">RERA number</dt>
                            <dd class="text-[13px] font-medium text-ink mt-0.5">{{ $property->rera_number ?? '—' }}</dd>
                        </div>
                    </dl>

                    @if($errors->any())
                        <div class="flex items-start gap-2.5 rounded-lg bg-danger-soft ring-1 ring-inset ring-danger-ring px-3.5 py-3 mb-4">
                            <x-icon name="x" class="w-4 h-4 text-danger shrink-0 mt-0.5" />
                            <div class="text-[12.5px] text-ink-2 leading-relaxed">
                                @foreach($errors->all() as $error)
                                    <p>{{ $error }}</p>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <form action="{{ $actionUrl }}" method="POST" class="space-y-4">
                        @csrf

                        @if($isAccept)
                            <p class="text-[13px] text-ink-2 leading-relaxed">
                                Accepting makes <strong class="text-ink">{{ $property->name }}</strong> visible to
                                channel partners in the {{ config('app.name') }} app right away.
                            </p>
                            <x-button variant="gold" size="lg" tag="button" type="submit" class="w-full" icon="check">
                                Confirm acceptance
                            </x-button>
                        @else
                            <x-field label="Reason for declining" name="reason" type="textarea" rows="3"
                                     value="{{ old('reason') }}" placeholder="Let the admin know why this project isn't a fit right now" required />
                            <x-button variant="danger" size="lg" tag="button" type="submit" class="w-full" icon="x">
                                Confirm decline
                            </x-button>
                        @endif
                    </form>
                </div>
            </div>

            <p class="text-[11.5px] text-ink-3 text-center mt-6">
                This link is unique to you and this project — no sign-in required.
            </p>
        </div>
    </div>
</x-layouts.guest>
