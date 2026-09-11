@props([
    /** The openable URL — already resolved through FileStorage::url(). */
    'url' => null,
    /** Shown as the title in the overlay. Falls back to the file's own basename. */
    'name' => null,
    /**
     * The stored path, used to work out which viewer the file needs. Prefer this over
     * `url`: a signed S3 link hides the extension behind a query string, and the path
     * never does. See App\Support\FilePreview.
     */
    'path' => null,
    /** Override the detected kind: 'image' | 'pdf' | 'video' | 'file'. */
    'kind' => null,
    /**
     * Name a set so the overlay's arrows step through it. Every link sharing a group on
     * the same page becomes one gallery, in document order.
     */
    'group' => null,
])

@php
    $previewUrl = $url;
    $previewName = $name ?: ($path ? basename($path) : null);
    $previewKind = $kind ?: \App\Support\FilePreview::kind($path ?: $previewUrl);

    /**
     * One payload, read by both the click handler and the overlay's group collection —
     * duplicating it into an x-on: expression would be a second place to keep in step.
     *
     * json_encode inside {{ }}: Blade escapes the quotes to &quot; entities, which the
     * HTML parser decodes back before dataset ever sees them, so this arrives at
     * JSON.parse as valid JSON. Js::from is the wrong tool here — it hex-escapes the
     * structural quotes for a JS expression context, which JSON.parse rejects.
     */
    $previewItem = json_encode([
        'url' => $previewUrl,
        'name' => $previewName,
        'kind' => $previewKind,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp

@if($previewUrl)
    {{--
        Still a real <a href>: ctrl/cmd/middle-click opens the file in a new tab exactly as
        before, and the link works with no JavaScript at all. The overlay is what a plain
        left-click gets, nothing more — which is why the handler checks the modifiers
        rather than using x-on:click.prevent, since that would swallow those too.
    --}}
    <a href="{{ $previewUrl }}" target="_blank" rel="noopener"
       @if($group) data-preview-group="{{ $group }}" @endif
       data-preview-item="{{ $previewItem }}"
       x-on:click="
           if ($event.button === 0 && ! ($event.metaKey || $event.ctrlKey || $event.shiftKey || $event.altKey)) {
               $event.preventDefault();
               $dispatch('file-preview', {
                   el: $el,
                   group: $el.dataset.previewGroup || null,
                   ...JSON.parse($el.dataset.previewItem),
               });
           }
       "
       {{ $attributes }}>
        {{ $slot }}
    </a>
@else
    {{-- No file attached. The caller's own placeholder is whatever it put in the slot. --}}
    {{ $slot }}
@endif
