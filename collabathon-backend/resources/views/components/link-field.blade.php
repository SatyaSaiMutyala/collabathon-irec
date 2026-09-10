@props([
    'label',
    'name',
    'placeholder' => null,
    'hint' => null,
])

{{--
    A URL <x-field> that shows what it points at, live as it is typed.

    A bare text box gives no signal that a pasted link is the right one, or even that it
    parses — a mistyped video URL looked identical to a good one until a broker opened the
    listing. YouTube links resolve to the video's own thumbnail (img.youtube.com serves it
    from the id alone, so there is no API call and no key); anything else that parses gets
    an open-in-new-tab chip; anything that does not parse says so.

    All state lives in this one x-data rather than a nested child: `valid` and `youTubeId`
    are real getters, so they resolve `this.url` lexically and would not see a `url` that
    belonged to an enclosing component.
--}}
@php
    // Same precedence x-field itself uses, so the preview matches the box on first paint —
    // including after a failed submit.
    $initial = old($name, data_get($formRecord ?? null, $name)) ?? '';
@endphp

<div x-data="{
    url: @js($initial),
    get trimmed() { return (this.url || '').trim(); },
    get valid() {
        if (! this.trimmed) { return false; }
        try {
            const parsed = new URL(this.trimmed);
            return parsed.protocol === 'http:' || parsed.protocol === 'https:';
        } catch (e) {
            return false;
        }
    },
    get youTubeId() {
        const match = this.trimmed.match(
            /(?:youtube\.com\/(?:watch\?(?:\S*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/
        );
        return match ? match[1] : null;
    },
}">
    <x-field :label="$label" :name="$name" type="url" :placeholder="$placeholder" :hint="$hint"
             icon="external" x-on:input="url = $event.target.value" />

    <div class="mt-2" x-show="trimmed.length" x-cloak>
        {{-- A YouTube link, shown as the video it points at. --}}
        <template x-if="valid && youTubeId">
            <a x-bind:href="trimmed" target="_blank" rel="noopener"
               class="flex items-center gap-2.5 rounded-lg border border-line bg-canvas p-2 hover:border-primary transition-colors">
                {{-- mqdefault exists for every video; maxresdefault does not, and 404s to a
                     broken image on anything not uploaded in HD. --}}
                <img x-bind:src="'https://img.youtube.com/vi/' + youTubeId + '/mqdefault.jpg'" alt=""
                     class="w-24 aspect-video rounded object-cover border border-line-soft shrink-0">
                <span class="min-w-0">
                    <span class="block text-[12px] font-medium text-ink">Video preview</span>
                    <span class="block text-[11.5px] text-ink-3 break-words" x-text="trimmed"></span>
                </span>
            </a>
        </template>

        {{-- Parses, but is not a video we can show a still for — Matterport walkthroughs
             land here, as does any other host. --}}
        <template x-if="valid && ! youTubeId">
            <a x-bind:href="trimmed" target="_blank" rel="noopener"
               class="flex items-center gap-2 rounded-lg border border-line bg-canvas px-2.5 py-2 hover:border-primary transition-colors">
                <x-icon name="external" class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                <span class="min-w-0 text-[11.5px] text-ink-2 break-words" x-text="trimmed"></span>
            </a>
        </template>

        <template x-if="! valid">
            <p class="flex items-start gap-1.5 text-[11.5px] text-ink-3">
                <x-icon name="x" class="w-3.5 h-3.5 shrink-0 mt-px" />
                <span>Enter a full link, starting with https://</span>
            </p>
        </template>
    </div>
</div>
