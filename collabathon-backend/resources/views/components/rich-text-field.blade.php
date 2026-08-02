@props([
    'name',
    'label' => null,
    'hint' => null,
    'placeholder' => 'Type the terms…',
    'rows' => 12,
])

@php
    $value = old($name, data_get(View::shared('formRecord'), $name) ?? '');
    $error = $errors->first($name);
@endphp

{{--
    Rich text without a build-time editor dependency.

    A contenteditable surface plus a toolbar, mirrored into a hidden <textarea> so the
    field posts like every other one in the wizard and needs no special handling in the
    controller. Alpine is already on the page; pulling in Quill or TipTap would add a
    bundle step to an admin panel whose only JS dependency today is image compression.

    Whatever comes out of here is sanitised server-side by App\Support\RichText before it
    is stored — the toolbar limits what an admin can *produce*, not what can be *posted*,
    so the allowlist is the actual boundary.
--}}
<div x-data="richText(@js($value))" class="min-w-0">
    @if($label)
        <label class="block text-[12.5px] font-medium text-ink-2 mb-1.5">{{ $label }}</label>
    @endif

    <div @class([
        'rounded-lg border overflow-hidden bg-panel',
        'border-danger' => $error,
        'border-line' => ! $error,
    ])>
        <div class="flex flex-wrap items-center gap-0.5 px-2 py-1.5 border-b border-line-soft bg-canvas">
            @foreach([
                ['bold', 'B', 'Bold', 'font-bold'],
                ['italic', 'I', 'Italic', 'italic'],
                ['underline', 'U', 'Underline', 'underline'],
            ] as [$command, $glyph, $title, $class])
                <button type="button" title="{{ $title }}"
                        x-on:click="run(@js($command))"
                        class="w-7 h-7 rounded text-[13px] text-ink-2 hover:bg-line-soft hover:text-ink transition-colors {{ $class }}">
                    {{ $glyph }}
                </button>
            @endforeach

            <span class="w-px h-4 bg-line mx-1"></span>

            @foreach([
                ['formatBlock', '<h3>', 'H', 'Heading'],
                ['insertUnorderedList', null, '•', 'Bullet list'],
                ['insertOrderedList', null, '1.', 'Numbered list'],
            ] as [$command, $argument, $glyph, $title])
                <button type="button" title="{{ $title }}"
                        x-on:click="run(@js($command), @js($argument))"
                        class="h-7 min-w-7 px-1.5 rounded text-[12.5px] font-medium text-ink-2 hover:bg-line-soft hover:text-ink transition-colors">
                    {{ $glyph }}
                </button>
            @endforeach

            <span class="w-px h-4 bg-line mx-1"></span>

            <button type="button" title="Add link" x-on:click="link()"
                    class="h-7 px-2 rounded text-[12px] text-ink-2 hover:bg-line-soft hover:text-ink transition-colors">
                Link
            </button>
            {{-- Paste is forced to plain text below, so this only clears formatting the
                 toolbar itself applied. --}}
            <button type="button" title="Clear formatting" x-on:click="run('removeFormat')"
                    class="h-7 px-2 rounded text-[12px] text-ink-3 hover:bg-line-soft hover:text-ink transition-colors ml-auto">
                Clear
            </button>
        </div>

        <div x-ref="editor"
             contenteditable="true"
             role="textbox"
             aria-multiline="true"
             data-placeholder="{{ $placeholder }}"
             x-on:input="sync()"
             x-on:blur="sync()"
             x-on:paste.prevent="pastePlain($event)"
             style="min-height: {{ $rows * 1.6 }}rem"
             class="rich-text px-3.5 py-3 text-[13px] leading-relaxed text-ink focus:outline-none overflow-y-auto max-h-[28rem]"></div>
    </div>

    {{-- The actual posted field. --}}
    <textarea name="{{ $name }}" x-ref="input" class="hidden">{{ $value }}</textarea>

    <div class="flex items-start justify-between gap-3 mt-1.5">
        <p class="text-[11.5px] {{ $error ? 'text-danger' : 'text-ink-3' }}">
            {{ $error ?: $hint }}
        </p>
        <p class="text-[11.5px] text-ink-3 nums shrink-0" x-text="words + (words === 1 ? ' word' : ' words')"></p>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function richText(initial) {
                return {
                    words: 0,

                    init() {
                        this.$refs.editor.innerHTML = initial || '';
                        this.count();
                    },

                    /**
                     * execCommand is deprecated but is still the only cross-browser way to
                     * apply formatting to a selection without shipping an editor. Every
                     * current engine implements it; the replacement (the Highlight/EditContext
                     * APIs) is not universally available yet.
                     */
                    run(command, argument = null) {
                        this.$refs.editor.focus();
                        document.execCommand(command, false, argument);
                        this.sync();
                    },

                    link() {
                        const url = window.prompt('Link URL');
                        if (!url) return;
                        // Bare domains are the common input; without a scheme the browser
                        // resolves them against the admin panel's own origin.
                        this.run('createLink', /^[a-z][a-z0-9+.-]*:/i.test(url) ? url : `https://${url}`);
                    },

                    /**
                     * Pasting from Word or a PDF carries a payload of spans, styles and
                     * classes that the server strips anyway. Taking the plain text keeps
                     * what the admin sees identical to what gets stored.
                     */
                    pastePlain(event) {
                        const text = (event.clipboardData || window.clipboardData).getData('text/plain');
                        document.execCommand('insertText', false, text);
                        this.sync();
                    },

                    sync() {
                        const html = this.$refs.editor.innerHTML.trim();
                        // A contenteditable that has been emptied leaves <br> behind, which
                        // would post as "content" and light up the terms button on a project
                        // that has none.
                        this.$refs.input.value = ['<br>', '<div><br></div>', '<p><br></p>'].includes(html) ? '' : html;
                        this.count();
                    },

                    count() {
                        const text = (this.$refs.editor.textContent || '').trim();
                        this.words = text ? text.split(/\s+/).length : 0;
                    },
                };
            }
        </script>
    @endpush
@endonce
