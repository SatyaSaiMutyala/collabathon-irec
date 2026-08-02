@props(['tabs' => [], 'model' => 'tab'])

{{-- Renders the bar only; the page owns the Alpine scope holding `$model`. --}}
<div role="tablist" {{ $attributes->merge(['class' => 'flex items-center gap-1 border-b border-line']) }}>
    @foreach($tabs as $t)
        <button type="button"
                role="tab"
                @click="{{ $model }} = '{{ $t['key'] }}'"
                :aria-selected="({{ $model }} === '{{ $t['key'] }}').toString()"
                :class="{{ $model }} === '{{ $t['key'] }}'
                    ? 'text-ink border-primary'
                    : 'text-ink-3 border-transparent hover:text-ink-2'"
                class="relative -mb-px flex items-center gap-2 px-3.5 py-2.5 text-[13px] font-medium border-b-2 transition-colors">
            {{ $t['label'] }}
            @if(isset($t['count']))
                <span :class="{{ $model }} === '{{ $t['key'] }}' ? 'bg-primary-soft text-primary-dark' : 'bg-canvas text-ink-3'"
                      class="min-w-[18px] px-1.5 h-[18px] inline-flex items-center justify-center rounded-badge text-[10.5px] font-semibold nums transition-colors">
                    {{ $t['count'] }}
                </span>
            @endif
        </button>
    @endforeach
</div>
