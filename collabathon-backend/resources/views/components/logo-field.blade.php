@props(['label' => 'Logo', 'name', 'hint' => null, 'required' => false])

{{--
    Company-logo upload with a mandatory crop step (see cropTool() in resources/js/app.js) —
    a logo is a wide wordmark, and letting a raw, arbitrarily-shaped image through is exactly
    what produces the tiny-and-letterboxed logos this field exists to prevent. The real file
    input stays empty until the crop is applied, so an uncropped file can never reach the form.

    Not a retrofit of <x-file-field> — that component is shared by CSV bulk-import and
    property-photo uploads, which must not gain a crop step they have no use for.
--}}

@php
    $id = str_replace(['[]', '[', ']'], ['', '-', ''], $name);
    $hasError = $errors->has($name);
    $message = $errors->first($name);
@endphp

<div x-data="cropTool()" class="space-y-1.5">
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
           x-on:change="onFileChange($event)" class="hidden" {{ $attributes }}>

    {{-- Picker trigger --}}
    <div x-show="! editing && ! previewUrl" x-cloak>
        <button type="button" x-on:click="$refs.input.click()"
                @class([
                    'flex items-center gap-2.5 w-full h-10 px-3.5 rounded-lg bg-panel border border-dashed transition-colors',
                    'border-danger' => $hasError,
                    'border-line hover:border-primary hover:bg-canvas' => ! $hasError,
                ])>
            <x-icon name="upload" class="w-4 h-4 text-ink-3 shrink-0" />
            <span class="text-[13px] text-ink-3">Choose a file…</span>
        </button>
    </div>

    {{-- Crop step --}}
    <div x-show="editing" x-cloak class="rounded-lg border border-line bg-canvas p-3 space-y-3">
        <p class="text-[11px] text-ink-3">Drag to reposition, use the slider to zoom, then apply.</p>

        {{-- 320×128 — must match LOGO_FRAME_WIDTH/LOGO_FRAME_HEIGHT in resources/js/app.js;
             the canvas element's own drawing-buffer size is set from those constants in
             cropTool's draw(), this is only its on-screen display size. --}}
        <canvas x-ref="canvas"
                style="width: 320px; height: 128px; touch-action: none; cursor: grab;"
                class="mx-auto block rounded-md bg-panel border border-line-soft shadow-card select-none"
                x-on:pointerdown="startDrag($event)"
                x-on:pointermove="onDrag($event)"
                x-on:pointerup="endDrag()"
                x-on:pointerleave="endDrag()"></canvas>

        <div class="flex items-center gap-2.5">
            <x-icon name="search" class="w-3.5 h-3.5 text-ink-3 shrink-0" />
            {{-- x-bind:disabled matters, not just x-show: this input sits inside a <form>
                 that submits via a real HTMLFormElement.submit() call, which means the
                 browser's native constraint validation still runs first — walking every
                 control in the form, hidden or not. Once a crop is applied this panel goes
                 display:none but stays in the DOM; if the browser then finds this range's
                 value briefly outside [min, max] (float drift between the bound scale and
                 minScale/minScale*3), it can't focus a hidden control to report it and
                 silently cancels the whole submit — Save changes appearing to do nothing,
                 with only a console warning to show for it. A disabled control is excluded
                 from constraint validation entirely, so this can never block a submit. --}}
            <input type="range" x-bind:min="minScale" x-bind:max="minScale * 3" step="0.001"
                   x-bind:value="scale" x-on:input="onZoom($event.target.value)"
                   x-bind:disabled="! editing"
                   class="w-full accent-primary" aria-label="Zoom">
        </div>

        <div class="flex items-center gap-2">
            <div class="flex-1"><x-button type="button" variant="outline" size="sm" x-on:click="cancel()" class="w-full">Cancel</x-button></div>
            <div class="flex-1"><x-button type="button" variant="gold" size="sm" x-on:click="apply()" class="w-full">Apply crop</x-button></div>
        </div>
    </div>

    {{-- Cropped result --}}
    <div x-show="! editing && previewUrl" x-cloak class="flex items-center gap-3">
        {{-- No rounding, ring or padding — matches avatar.blade.php's square/logo mode, so
             this preview looks the same as the logo will everywhere else once saved. --}}
        <img x-bind:src="previewUrl" alt=""
             class="w-[110px] h-[44px] object-contain bg-panel border border-line-soft">
        <button type="button" x-on:click="$refs.input.click()"
                class="text-[12px] font-medium text-primary-dark hover:underline">
            Change
        </button>
    </div>

    @if($hasError)
        <p class="flex items-start gap-1.5 text-[11.5px] text-danger">
            <x-icon name="x" class="w-3.5 h-3.5 shrink-0 mt-px" />
            <span>{{ $message }}</span>
        </p>
    @elseif($hint)
        <p class="text-[11.5px] text-ink-3">{{ $hint }}</p>
    @endif
</div>
