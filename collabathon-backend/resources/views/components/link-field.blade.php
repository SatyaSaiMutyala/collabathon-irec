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
    /*
     * img.youtube.com is reachable from most places but not all, and the still itself
     * can be missing — a deleted or private video has no thumbnail, and a filtering
     * proxy (a FortiGate intercepting Google domains is the one we hit) blocks the host
     * outright. Any of those left a browser's broken-image glyph sitting in the form,
     * which reads as "the panel is broken" rather than "the picture didn't load". The
     * link is what matters here; the still is decoration, so it fails to a tile.
     */
    thumbFailed: false,
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
    {{-- thumbFailed resets with the box: a still that could not load for the previous
         link says nothing about the one being typed now. --}}
    <x-field :label="$label" :name="$name" type="url" :placeholder="$placeholder" :hint="$hint"
             icon="external" x-on:input="url = $event.target.value; thumbFailed = false" />

    <div class="mt-2" x-show="trimmed.length" x-cloak>
        {{-- A YouTube link, shown as the video it points at. --}}
        <template x-if="valid && youTubeId">
            <a x-bind:href="trimmed" target="_blank" rel="noopener"
               class="flex items-center gap-2.5 rounded-lg border border-line bg-canvas p-2 hover:border-primary transition-colors">
                {{-- The still and its stand-in share one fixed box, so the card keeps its
                     shape whichever renders and the text never shifts. --}}
                <span class="relative w-24 aspect-video rounded border border-line-soft shrink-0 overflow-hidden bg-surface flex items-center justify-center">
                    {{-- mqdefault exists for every video; maxresdefault does not, and 404s to a
                         broken image on anything not uploaded in HD. --}}
                    <img x-show="! thumbFailed"
                         x-bind:src="'https://img.youtube.com/vi/' + youTubeId + '/mqdefault.jpg'" alt=""
                         x-on:error="thumbFailed = true"
                         x-on:load="thumbFailed = false"
                         class="absolute inset-0 w-full h-full object-cover">
                    <x-icon name="youtube" x-show="thumbFailed" x-cloak class="w-5 h-5 text-ink-3" />
                </span>
                <span class="min-w-0">
                    {{-- Named for what is on screen: with no still to show, "Video preview"
                         labels an empty box, while the id is the thing worth reading back. --}}
                    <span class="block text-[12px] font-medium text-ink"
                          x-text="thumbFailed ? 'YouTube video · ' + youTubeId : 'Video preview'"></span>
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
