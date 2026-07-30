@props(['align' => 'right', 'width' => 'w-56'])

{{--
    The menu is teleported to <body> and positioned with fixed coordinates rather than
    living inside the trigger's stacking context. x-data-table wraps its table in
    `overflow-x-auto`, which clips (and scrolls away) any absolutely-positioned child —
    so the last row's menu was being cut off whenever there were no rows below it.
    Teleporting escapes that clip; place() then flips the menu above the trigger when
    there isn't room underneath.
--}}
<div x-data="dropdownMenu(@js($align))" class="relative">
    <div x-ref="trigger" @click="toggle()" :aria-expanded="open.toString()" aria-haspopup="menu">
        {{ $trigger }}
    </div>

    <template x-teleport="body">
        <div x-ref="menu"
             x-show="open"
             x-cloak
             @click.outside="onOutside($event)"
             @keydown.escape.window="close()"
             x-transition:enter="transition ease-out duration-120"
             x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-90"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             role="menu"
             :style="`top:${top}px; left:${left}px`"
             {{-- Below the dialog layers (z-55/60/65) so a modal opened from a menu item
                  covers the menu rather than the other way round. --}}
             class="fixed z-[45] {{ $width }} bg-panel border border-line rounded-xl shadow-pop py-1.5">
            {{ $slot }}
        </div>
    </template>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                const EDGE = 8;   // viewport padding
                const GAP = 6;    // space between trigger and menu

                Alpine.data('dropdownMenu', (align = 'right') => ({
                    open: false,
                    top: 0,
                    left: 0,

                    init() {
                        // Capture phase catches scrolling of the inner table/main containers
                        // too, not just the window — the menu must track its trigger.
                        this.reposition = () => this.open && this.place();
                        window.addEventListener('scroll', this.reposition, true);
                        window.addEventListener('resize', this.reposition);
                    },

                    destroy() {
                        window.removeEventListener('scroll', this.reposition, true);
                        window.removeEventListener('resize', this.reposition);
                    },

                    toggle() {
                        this.open ? this.close() : this.show();
                    },

                    show() {
                        this.open = true;
                        // Measured after paint, so offsetWidth/Height are real.
                        this.$nextTick(() => this.place());
                    },

                    close() {
                        this.open = false;
                    },

                    // The trigger's own click already toggles; without this guard the
                    // outside-click handler would close the menu in the same tick it opens.
                    onOutside(event) {
                        if (! this.$refs.trigger.contains(event.target)) this.close();
                    },

                    place() {
                        const trigger = this.$refs.trigger.getBoundingClientRect();
                        const menu = this.$refs.menu;
                        const width = menu.offsetWidth;
                        const height = menu.offsetHeight;

                        // Prefer below; flip above when the bottom edge would overflow.
                        let top = trigger.bottom + GAP;
                        if (top + height > window.innerHeight - EDGE) {
                            const above = trigger.top - GAP - height;
                            top = above >= EDGE ? above : Math.max(EDGE, window.innerHeight - height - EDGE);
                        }

                        let left = align === 'left' ? trigger.left : trigger.right - width;
                        left = Math.min(Math.max(EDGE, left), window.innerWidth - width - EDGE);

                        this.top = top;
                        this.left = left;
                    },
                }));
            });
        </script>
    @endpush
@endonce
