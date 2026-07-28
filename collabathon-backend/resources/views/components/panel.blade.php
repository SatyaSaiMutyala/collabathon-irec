@props(['title' => null])

<div {{ $attributes->merge(['class' => 'bg-white border border-border rounded-xl overflow-hidden']) }}>
    @if($title)
        <div class="px-5 py-4 border-b border-border flex items-center justify-between">
            <h2 class="text-[14px] font-semibold text-navy">{{ $title }}</h2>
            @isset($actions)
                <div>{{ $actions }}</div>
            @endisset
        </div>
    @endif
    {{ $slot }}
</div>
