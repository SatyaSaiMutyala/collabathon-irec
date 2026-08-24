@props([
    'label' => 'Photo',
    'name',
    'hint' => null,
    'required' => false,
    'multiple' => false,
    // Landscape, matching the fixed-height, full-width banner/card this feeds on the
    // mobile app (resizeMode "cover" — see SwipeableImages.js) — a mismatched ratio here
    // just means `cover` has to crop more at display time, exactly what this mandatory
    // crop step exists to put in the admin's own hands instead of leaving to chance.
    'ratio' => 4 / 3,
    // 1600×1200 at this ratio — comfortably sharp on a high-DPI phone screen (~3x device
    // pixel ratio over a ~390–430pt-wide hero) without ballooning file size before
    // compressFileInputs() gets a chance to re-encode it.
    'outputWidth' => 1600,
    // A literal Tailwind aspect-ratio class matching $ratio — passed as a class rather
    // than reconstructed from the float, so the compiled CSS is guaranteed to exist
    // (arbitrary-value classes only ship if Tailwind's build actually scans the literal
    // string somewhere) and stays pixel-exact with cropTool's own frame math.
    'aspectClass' => 'aspect-[4/3]',
])

{{--
    Landscape-photo upload with a mandatory crop step (see cropTool() in resources/js/app.js)
    — property cover/gallery photos get shown edge-to-edge in a fixed-ratio frame on the
    mobile app, and a raw, arbitrarily-shaped photo is exactly what gets its edges cut off
    there. The real file input stays empty until at least one crop is applied, so an
    uncropped file can never reach the form.

    Not a retrofit of <x-file-field> — that component is shared by CSV bulk-import and
    document uploads (PDFs, floor plans) that have no use for a photo crop step.

    $multiple turns this into a crop queue: every file picked at once is cropped one after
    another against the same frame, and results accumulate so a second "Add more" pick
    appends rather than starting over. Mirrors <x-logo-field>, generalized to a configurable
    ratio/output size instead of the fixed 5:2 wordmark crop.
--}}

@php
    $id = str_replace(['[]', '[', ']'], ['', '-', ''], $name);
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $hasError = $errors->has($errorKey) || $errors->has($errorKey . '.*');
    $message = $errors->first($errorKey) ?: $errors->first($errorKey . '.*');

    $frameWidth = 320;
    $frameHeight = (int) round($frameWidth / $ratio);
@endphp

<div x-data="cropTool({ ratio: {{ $ratio }}, outputWidth: {{ $outputWidth }}, multiple: {{ $multiple ? 'true' : 'false' }} })"
     class="space-y-1.5">
    @if($label)
        <label class="flex items-center gap-1 text-[12.5px] font-medium text-ink">
            {{ $label }}
            @if($required)<span class="text-danger" aria-hidden="true">*</span>@endif
        </label>
    @endif

    {{-- `hidden` rather than the sr-only technique x-file-field uses: this input is only
         ever opened via a JS-triggered .click() on the buttons below, never via a native
         <label for>, so there's no risk of the browser scrolling a focused-but-invisible
         box into view — a display:none element can't receive that focus at all. --}}
    <input x-ref="input" id="{{ $id }}" type="file" name="{{ $name }}" accept="image/*"
           @if($multiple) multiple @endif
           x-on:change="onFileChange($event)" class="hidden" {{ $attributes }}>

    {{-- Picker trigger. In multiple mode this stays visible even once results exist, so
         "Add more" is always reachable — the single-file variant swaps to a result view
         instead once a crop is applied. --}}
    <div x-show="{{ $multiple ? 'true' : '! editing && ! previewUrl' }}" x-cloak>
        <button type="button" x-on:click="$refs.input.click()"
                @class([
                    'flex items-center gap-2.5 w-full h-10 px-3.5 rounded-lg bg-panel border border-dashed transition-colors',
                    'border-danger' => $hasError,
                    'border-line hover:border-primary hover:bg-canvas' => ! $hasError,
                ])>
            <x-icon name="upload" class="w-4 h-4 text-ink-3 shrink-0" />
            <span class="text-[13px] text-ink-3" x-text="results.length ? 'Add more…' : 'Choose {{ $multiple ? 'files' : 'a file' }}…'"></span>
        </button>
    </div>

    {{-- Crop step --}}
    <div x-show="editing" x-cloak class="rounded-lg border border-line bg-canvas p-3 space-y-3">
        <p class="text-[11px] text-ink-3">
            Drag to reposition, use the slider to zoom, then apply.
            @if($multiple)
                <span x-show="queue.length > 1" x-text="'Photo ' + (queueIndex + 1) + ' of ' + queue.length + '.'"></span>
            @endif
        </p>

        {{-- Must match the frameWidth/frameHeight cropTool() derives from this field's own
             ratio — the canvas element's drawing-buffer size is set from those in
             cropTool's draw(), this is only its on-screen display size. --}}
        <canvas x-ref="canvas"
                style="width: {{ $frameWidth }}px; height: {{ $frameHeight }}px; touch-action: none; cursor: grab;"
                class="mx-auto block rounded-md bg-panel border border-line-soft shadow-card select-none max-w-full"
                x-on:pointerdown="startDrag($event)"
                x-on:pointermove="onDrag($event)"
                x-on:pointerup="endDrag()"
                x-on:pointerleave="endDrag()"></canvas>

        <div class="flex items-center gap-2.5">
            <x-icon name="search" class="w-3.5 h-3.5 text-ink-3 shrink-0" />
            {{-- x-bind:disabled matters, not just x-show — see <x-logo-field> for why: a
                 hidden-but-out-of-range range input silently cancels the whole form submit. --}}
            <input type="range" x-bind:min="minScale" x-bind:max="minScale * 3" step="0.001"
                   x-bind:value="scale" x-on:input="onZoom($event.target.value)"
                   x-bind:disabled="! editing"
                   class="w-full accent-primary" aria-label="Zoom">
        </div>

        {{-- No processing recovers detail a source photo never had — this only ever
             warns, it never blocks Apply, since the server still accepts whatever crop
             the admin decides on. --}}
        <p x-show="lowResolution" x-cloak class="flex items-start gap-1.5 text-[11px] text-warning">
            <x-icon name="shield" class="w-3.5 h-3.5 shrink-0 mt-px" />
            <span>This photo is lower resolution than the crop target and may look soft once cropped — a larger source photo will look sharper.</span>
        </p>

        <div class="flex items-center gap-2">
            <div class="flex-1"><x-button type="button" variant="outline" size="sm" x-on:click="cancel()" class="w-full">Cancel</x-button></div>
            <div class="flex-1"><x-button type="button" variant="gold" size="sm" x-on:click="apply()" class="w-full">Apply crop</x-button></div>
        </div>
    </div>

    @if($multiple)
        {{-- Cropped results this session --}}
        <div x-show="! editing && results.length" x-cloak class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-2.5">
            <template x-for="(result, index) in results" :key="result.previewUrl">
                <div class="relative">
                    <img x-bind:src="result.previewUrl" alt=""
                         class="w-full {{ $aspectClass }} object-cover rounded-lg border border-line-soft">
                    <button type="button" x-on:click="removeResult(index)"
                            class="absolute inset-x-1 bottom-1.5 text-[11px] font-medium text-danger hover:underline bg-panel/90 rounded">
                        Remove
                    </button>
                </div>
            </template>
        </div>
    @else
        {{-- Cropped result --}}
        <div x-show="! editing && previewUrl" x-cloak class="flex items-center gap-3">
            {{-- Fixed literal size (not derived from $ratio) — Tailwind's build only ships
                 an arbitrary-value class if the exact string appears somewhere in source,
                 so this can't be reconstructed from a PHP variable. 160×120 = 4:3, matching
                 the only caller that uses single mode (cover_image); object-cover fills it
                 either way even if a future caller passes a different $ratio. --}}
            <img x-bind:src="previewUrl" alt=""
                 class="w-[160px] h-[120px] object-cover rounded-lg border border-line-soft">
            <button type="button" x-on:click="$refs.input.click()"
                    class="text-[12px] font-medium text-primary-dark hover:underline">
                Change
            </button>
        </div>
    @endif

    @if($hasError)
        <p class="flex items-start gap-1.5 text-[11.5px] text-danger">
            <x-icon name="x" class="w-3.5 h-3.5 shrink-0 mt-px" />
            <span>{{ $message }}</span>
        </p>
    @elseif($hint)
        <p class="text-[11.5px] text-ink-3">{{ $hint }}</p>
    @endif
</div>
