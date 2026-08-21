@props(['tabs' => [], 'model' => 'tab'])

{{--
    Renders the bar only; the page owns the Alpine scope holding `$model`.

    `overflow-x-auto` matters once there are enough tabs to outrun the page's own
    max-width container (Settings has 10+) — without it the row doesn't wrap or clip,
    it just bleeds past the container's right padding and out to the edge of the
    viewport, same problem the wide data tables elsewhere on this page already solve
    with the same `overflow-x-auto scrollbar-slim` pairing. `shrink-0` per tab keeps
    flex from squeezing labels instead of letting the row scroll.

    `x-effect` re-runs whenever `{{ $model }}` changes — including once, immediately,
    on first paint, which is what covers a page loading directly onto a tab that
    happens to be scrolled out of view (a redirect landing on `?tab=access`, say), not
    just a later click. `block: 'nearest'` keeps this to the row's own horizontal
    scroll only — without it, scrollIntoView is free to also move the whole page
    vertically to "better" centre the element, which a tab click has no business doing.

    `active` is read synchronously, before the $nextTick — Alpine's dependency
    tracking only sees property reads made during the effect's own synchronous run, so
    reading `{{ $model }}` *inside* the deferred callback instead would never
    re-trigger this on a later change; only the DOM query itself needs deferring, to
    let the :class-driven active-tab styling paint first.
--}}
<div role="tablist"
     x-effect="
         const active = {{ $model }};
         $nextTick(() => $el.querySelector(`[data-tab-key='${active}']`)?.scrollIntoView({behavior: 'smooth', inline: 'center', block: 'nearest'}));
     "
     {{ $attributes->merge(['class' => 'flex items-center gap-1 border-b border-line overflow-x-auto scrollbar-slim']) }}>
    @foreach($tabs as $t)
        <button type="button"
                role="tab"
                data-tab-key="{{ $t['key'] }}"
                @click="{{ $model }} = '{{ $t['key'] }}'"
                :aria-selected="({{ $model }} === '{{ $t['key'] }}').toString()"
                :class="{{ $model }} === '{{ $t['key'] }}'
                    ? 'text-ink border-primary'
                    : 'text-ink-3 border-transparent hover:text-ink-2'"
                class="relative -mb-px flex items-center gap-2 px-3.5 py-2.5 text-[13px] font-medium border-b-2 transition-colors shrink-0 whitespace-nowrap">
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
