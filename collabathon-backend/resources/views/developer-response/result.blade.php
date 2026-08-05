@php
    $accepted = $status === \App\Models\Property::DEV_ACCEPTED;
@endphp
<x-layouts.guest :title="($accepted ? 'Accepted' : 'Declined') . ' — ' . $property->name">
    <div class="min-h-screen flex items-center justify-center px-5 py-12 bg-canvas">
        <div class="w-full max-w-[440px] text-center">

            <div class="w-14 h-14 rounded-full mx-auto flex items-center justify-center {{ $accepted ? 'bg-success-soft' : 'bg-canvas ring-1 ring-inset ring-line' }}">
                <x-icon :name="$accepted ? 'check' : 'x'" class="w-6 h-6 {{ $accepted ? 'text-success' : 'text-ink-3' }}" />
            </div>

            <h1 class="text-[20px] font-semibold text-ink tracking-[-0.01em] mt-5">
                {{ $accepted ? 'Project accepted' : 'Project declined' }}
            </h1>
            <p class="text-[13.5px] text-ink-2 mt-2 leading-relaxed">
                @if($accepted)
                    <strong class="text-ink">{{ $property->name }}</strong> is now visible to channel partners
                    in the {{ config('app.name') }} app.
                @else
                    <strong class="text-ink">{{ $property->name }}</strong> has been marked as declined.
                    The admin has been notified.
                @endif
            </p>

            <p class="text-[11.5px] text-ink-3 mt-8">
                You can close this window. You can change this from your account at any time.
            </p>
        </div>
    </div>
</x-layouts.guest>
