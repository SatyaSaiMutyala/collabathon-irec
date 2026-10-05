@props([
    'label' => null,
    'name',
    'hint' => null,
    'accept' => null,
    'multiple' => false,
    'required' => false,
    'icon' => 'download',
    'current' => null,   // storage path already on record — edit forms pass this
    // The record's property_media.id for $current, when it has one — lets its card post
    // into the same remove_media[] the gallery already uses, so "Remove" here deletes it
    // on save too.
    'currentId' => null,
    // For a $current that is a plain column (cover_image_path, logo_path — not a
    // property_media row, so no id) whose controller has been taught to read a matching
    // clear_<name> flag. Pass the input name to post, e.g. "logo" → clear_logo.
    //
    // Both are left null by callers with no removal wiring at all (KYC docs, broker
    // photos) — the card still renders there, just without a Remove button, rather than
    // showing one that looks like it works but silently does nothing on save.
    'currentClearField' => null,
])

{{-- Native file inputs cannot be repopulated by old(), so a failed submit always loses the
     selection. The filename readout is Alpine-local and exists to make that obvious rather
     than leave the control looking filled when it is empty. --}}
@php
    // Str::slug() would rewrite `cover_image` to `cover-image`, leaving the id out of step
    // with the field name and with x-field, which uses the name as-is. Only the array
    // brackets need stripping to make a valid id.
    $id = str_replace(['[]', '[', ']'], ['', '-', ''], $name);
    // Array inputs (`gallery[]`) report errors on the base key and on each index.
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $hasError = $errors->has($errorKey) || $errors->has($errorKey . '.*');
    $message = $errors->first($errorKey) ?: $errors->first($errorKey . '.*');

    // Whether the file already on record can be shown as a picture. Decided from the
    // extension because that is all the stored path gives us — there is no mime column.
    $currentIsImage = $current && in_array(
        strtolower(pathinfo($current, PATHINFO_EXTENSION)),
        ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'],
        true,
    );
@endphp

{{-- `files` holds File objects, not names, because in multiple mode the selection is
     rewritten back onto the input (see sync()) and only real File objects can be.

     A native multi-file input replaces its whole selection on every pick and offers no way
     to drop one file, so picking a fifth image meant re-picking all five, and a mis-picked
     one meant starting over. Holding the list here and assigning it back through a
     DataTransfer is what makes "add more" append and "remove" possible at all. It also
     fixes a quieter native annoyance: opening the picker and cancelling clears the input,
     and sync() puts the accumulated list straight back. --}}
<div {{ $attributes->only('class') }} x-data="{
    files: [],
    /**
     * One entry per file: the File itself, plus a thumbnail URL when it is an image.
     * The URL is minted once here rather than in the markup — an object URL built inside
     * an x-bind would be rebuilt on every re-render and leak one blob per keystroke.
     */
    entryFor(file) {
        return { file, url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null };
    },
    /** Push our list back onto the input, since that list is what actually gets posted. */
    sync() {
        const transfer = new DataTransfer();
        this.files.forEach(entry => transfer.items.add(entry.file));
        this.$refs.input.files = transfer.files;
    },
    release(entry) {
        if (entry && entry.url) { URL.revokeObjectURL(entry.url); }
    },
    picked(event) {
        const incoming = Array.from(event.target.files);

        if (! {{ $multiple ? 'true' : 'false' }}) {
            this.files.forEach(entry => this.release(entry));
            this.files = incoming.map(file => this.entryFor(file));
            return;
        }

        // Same name and size twice over is the same file picked twice — the browser has
        // no notion of that across two separate picks, so it would otherwise be uploaded
        // and stored as a duplicate.
        incoming.forEach(file => {
            if (! this.files.some(held => held.file.name === file.name && held.file.size === file.size)) {
                this.files.push(this.entryFor(file));
            }
        });

        this.sync();
    },
    remove(index) {
        this.release(this.files.splice(index, 1)[0]);
        this.sync();
    },
    /* Single-file mode only — sync()'s DataTransfer rebuild is for the multi-file
       append/remove case; clearing a lone native file input back to empty just needs
       its own value reset. Without this there was no way back to nothing-chosen
       short of picking a different file to replace it with. */
    removeSingle() {
        this.files.forEach(entry => this.release(entry));
        this.files = [];
        this.$refs.input.value = '';
    },
}">
    @if($label)
        <label for="{{ $id }}" class="flex items-center gap-1 text-[12.5px] font-medium text-ink mb-1.5">
            {{ $label }}
            @if($required)<span class="text-danger" aria-hidden="true">*</span>@endif
        </label>
    @endif

    {{-- `relative`, so the absolutely-positioned input below resolves against this label
         rather than against the page. Solid border + canvas fill once a single file is
         picked, not the dashed dropzone look — dashed reads as "drop something here",
         which stops being true the moment there is a real attached file sitting in it;
         a chat app's own attachment preview is solid for the same reason. Multi-file
         mode keeps the dropzone dashed throughout, since it is always still inviting
         more files — its picked list renders as its own block below instead. --}}
    <label for="{{ $id }}"
           @class([
               'relative flex items-center gap-2.5 w-full min-h-10 px-3.5 py-2 rounded-lg cursor-pointer transition-colors',
               'border-danger border bg-panel' => $hasError,
               'border-line border-dashed border hover:border-primary hover:bg-canvas bg-panel' => ! $hasError && $multiple,
           ])
           @if(! $multiple)
               {{-- Picked state sizes to its content (a bounded card, like a chat
                    attachment) instead of stretching the dropzone's full width —
                    `inline-flex` + a max-width lets the row be only as wide as the
                    filename needs, with `truncate` still catching a long one. --}}
               x-bind:class="files.length
                   ? 'inline-flex w-auto max-w-sm {{ $hasError ? 'border-danger border bg-panel' : 'border border-line bg-canvas' }}'
                   : 'flex w-full {{ $hasError ? 'border-danger border bg-panel' : 'border border-dashed border-line hover:border-primary hover:bg-canvas bg-panel' }}'"
           @endif>
        <x-icon :name="$icon" class="w-4 h-4 text-ink-3 shrink-0" x-show="! files.length" />

        {{-- min-w-0 is what makes `truncate` work on a flex child: without it the item's
             min-width resolves to its content, so a long placeholder widens the row instead
             of ellipsing inside it. --}}
        <span class="text-[13px] text-ink-3 truncate min-w-0" x-show="! files.length">
            {{ $multiple ? 'Choose files…' : 'Choose a file…' }}
        </span>

        @if($multiple)
            {{-- The names themselves are listed underneath, so this line only has to say
                 how many there are and stay an obvious way back into the picker. --}}
            <span class="text-[13px] text-ink min-w-0" x-show="files.length" x-cloak
                  x-text="files.length === 1 ? '1 file selected — choose more…' : files.length + ' files selected — choose more…'"></span>
        @else
            {{-- Picked state: a proper attachment card (icon badge, filename, type +
                 size, remove) instead of a cramped inline filename — the earlier
                 version read as "is this actually attached?" rather than looking like
                 a real file the way a chat app's own attachment preview does. --}}
            <template x-if="files.length">
                <div class="flex items-center gap-3 flex-1 min-w-0 py-1" x-cloak>
                    <template x-if="files[0]?.url">
                        <img x-bind:src="files[0].url" alt=""
                             class="w-11 h-11 rounded-lg object-cover border border-line-soft shrink-0">
                    </template>
                    <template x-if="! files[0]?.url">
                        <span class="w-11 h-11 rounded-lg bg-danger-soft grid place-items-center shrink-0">
                            <x-icon name="file-text" class="w-5 h-5 text-danger" />
                        </span>
                    </template>
                    <div class="flex-1 min-w-0">
                        <p class="text-[13px] font-medium text-ink truncate"
                           x-bind:title="files[0]?.file.name" x-text="files[0]?.file.name"></p>
                        <p class="text-[11.5px] text-ink-3 mt-0.5"
                           x-text="(files[0]?.file.name.split('.').pop() || '').toUpperCase() + ' · ' +
                                   (files[0]?.file.size > 1048576
                                       ? (files[0].file.size / 1048576).toFixed(1) + ' MB'
                                       : Math.max(1, Math.round(files[0]?.file.size / 1024)) + ' KB')"></p>
                    </div>
                    {{-- stop+prevent: this whole control is a <label for=input>, whose
                         default behaviour is reopening the file picker on any click
                         inside it — without these the remove button would clear the
                         selection and immediately reopen the picker on top of it.
                         relative z-10: the transparent file input below is absolutely
                         positioned over the whole label (so a click anywhere else still
                         reopens the picker) and comes after this button in the DOM — a
                         positioned element with no z-index still paints above a static
                         one, so without this the input silently ate every click meant
                         for the button and the remove action never fired at all. --}}
                    <button type="button" x-on:click.stop.prevent="removeSingle()"
                            class="relative z-10 text-danger hover:bg-danger-soft rounded-md p-1.5 shrink-0 transition-colors"
                            aria-label="Remove file">
                        <x-icon name="x" class="w-4 h-4" />
                    </button>
                </div>
            </template>
        @endif

        {{-- Transparent and stretched over the label rather than `sr-only`.

             sr-only is `position:absolute` with a 1px box and no positioned ancestor, so
             the input sat at its static position — below the visible row, and often below
             the fold. Opening the picker focuses it, and the browser scrolls that 1px box
             into view: the page jumped ~250px the moment the file dialog appeared.

             Covering the label instead means the focused box is the box the user just
             clicked, so scrolling it into view is a no-op. It stays a real focusable
             input, so keyboard and screen-reader access are unchanged. --}}
        <input id="{{ $id }}" name="{{ $name }}" type="file" x-ref="input"
               @if($multiple) multiple @endif
               @if($accept) accept="{{ $accept }}" @endif
               @if($required) required @endif
               x-on:change="picked($event)"
               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
               {{ $attributes->except('class') }}>
    </label>

    @if($multiple)
        {{-- One row per file, each removable. Sits outside the label above: a click inside
             that label reopens the file picker, which is the last thing a remove button
             should do. --}}
        <ul class="mt-1.5 space-y-1" x-show="files.length" x-cloak>
            <template x-for="(entry, index) in files" :key="entry.file.name + entry.file.size">
                <li class="flex items-center gap-2 rounded-lg bg-canvas border border-line-soft px-2.5 py-1.5">
                    {{-- Thumbnail for an image, the generic file glyph for anything else
                         (a PDF cannot be previewed without a renderer). --}}
                    <template x-if="entry.url">
                        <img x-bind:src="entry.url" alt=""
                             class="w-9 h-9 rounded object-cover border border-line-soft shrink-0">
                    </template>
                    <template x-if="! entry.url">
                        <span class="w-9 h-9 rounded bg-panel border border-line-soft grid place-items-center shrink-0">
                            <x-icon name="download" class="w-3.5 h-3.5 text-ink-3" />
                        </span>
                    </template>
                    <span class="flex-1 min-w-0 text-[12px] text-ink-2 break-words leading-snug"
                          x-bind:title="entry.file.name" x-text="entry.file.name"></span>
                    <span class="text-[11px] text-ink-3 nums shrink-0"
                          x-text="entry.file.size > 1048576
                              ? (entry.file.size / 1048576).toFixed(1) + ' MB'
                              : Math.max(1, Math.round(entry.file.size / 1024)) + ' KB'"></span>
                    <button type="button" x-on:click="remove(index)"
                            class="text-ink-3 hover:text-danger rounded-md p-1 -m-1 shrink-0 transition-colors"
                            x-bind:aria-label="'Remove ' + entry.file.name">
                        <x-icon name="x" class="w-3.5 h-3.5" />
                    </button>
                </li>
            </template>
        </ul>
    @endif

    {{-- A file input cannot be pre-filled, so on an edit the stored file is shown beside it
         as the same attachment card the freshly-picked state above uses — thumbnail or
         file-type badge, bold filename, type + size. Hidden the moment a new file is
         picked, since that pick is the replacement, and a local `marked` flag lets
         Remove hide it immediately without waiting for a save. Only shown with a working
         Remove when $currentId is given: that is what lets it post into remove_media[],
         the same mechanism the gallery already uses, so deletion happens on save exactly
         like removing a gallery image does. --}}
    @if($current)
        @php
            $currentSize = \App\Support\FileStorage::size($current);
            $currentExt = strtoupper(pathinfo($current, PATHINFO_EXTENSION));
            // Storage renames every upload to a random hash before saving it (collision
            // safety), and nothing captures the original filename to show back later —
            // so basename($current) is that hash, not anything an admin picked. The
            // field's own label is what this file actually is in every case that
            // reaches here (one named slot per property, never a pool of unrelated
            // files), so "Brochure.pdf" stands in for it reliably.
            $currentName = ($label ?: 'File') . '.' . strtolower(pathinfo($current, PATHINFO_EXTENSION));
        @endphp
        @php $removable = $currentId || $currentClearField; @endphp
        <div class="mt-1.5" x-data="{ marked: false }" x-show="! files.length && ! marked" x-cloak>
            @if($currentId)
                <input type="checkbox" name="remove_media[]" value="{{ $currentId }}" x-model="marked" class="sr-only">
            @elseif($currentClearField)
                <input type="checkbox" name="clear_{{ $currentClearField }}" value="1" x-model="marked" class="sr-only">
            @endif
            <div class="relative inline-flex w-auto max-w-sm items-center gap-3 px-3.5 py-2 rounded-lg border border-line bg-canvas">
                @if($currentIsImage)
                    <img src="{{ \App\Support\FileStorage::url($current) }}" alt=""
                         class="w-11 h-11 rounded-lg object-cover border border-line-soft shrink-0">
                @else
                    <span class="w-11 h-11 rounded-lg bg-danger-soft grid place-items-center shrink-0">
                        <x-icon name="file-text" class="w-5 h-5 text-danger" />
                    </span>
                @endif
                <div class="flex-1 min-w-0">
                    <a href="{{ \App\Support\FileStorage::url($current) }}" target="_blank" rel="noopener"
                       class="block text-[13px] font-medium text-ink truncate hover:underline"
                       title="{{ $currentName }}">{{ $currentName }}</a>
                    <p class="text-[11.5px] text-ink-3 mt-0.5">
                        {{ $currentExt }}@if($currentSize) &middot; {{ $currentSize > 1048576 ? number_format($currentSize / 1048576, 1) . ' MB' : max(1, round($currentSize / 1024)) . ' KB' }}@endif
                    </p>
                </div>
                @if($removable)
                    <button type="button" @click="marked = true"
                            class="text-danger hover:bg-danger-soft rounded-md p-1.5 shrink-0 transition-colors"
                            aria-label="Remove file">
                        <x-icon name="x" class="w-4 h-4" />
                    </button>
                @endif
            </div>
            @if($removable)
                <p class="text-[11px] text-ink-3 mt-1">Removed when you save.</p>
            @endif
        </div>
    @endif

    @if($hasError)
        <p class="flex items-start gap-1.5 text-[11.5px] text-danger mt-1.5">
            <x-icon name="x" class="w-3.5 h-3.5 shrink-0 mt-px" />
            <span>{{ $message }}</span>
        </p>
    @elseif($hint)
        <p class="text-[11.5px] text-ink-3 mt-1.5">{{ $current ? 'Choose a file to replace it.' : $hint }}</p>
    @endif
</div>
