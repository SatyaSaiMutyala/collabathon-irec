{{--
    Step 2 of 3 — see Admin\PasswordResetController.

    One single-digit box per digit of PasswordResetOtp::CODE_LENGTH, over one hidden input:
    the boxes are what people expect from a code screen, and the script below keeps them
    behaving like one field (typing advances, backspace retreats, a pasted code fills them
    all). The real posted value is the hidden `code` input, so the form still submits
    correctly if the script never runs — see the note in auth.reset-password on why there
    is no Alpine here.
--}}
<x-layouts.guest title="Enter your code — iREC Admin">
    <x-auth.shell
        heading="Enter the code"
        :subheading="'We sent a ' . $length . '-digit code to ' . $masked . '. It expires in ' . $minutes . ' minutes.'"
        step="2">

        @if(session('debug_code'))
            {{-- Non-production only (PasswordResetController::exposesCode()) — the code is
                 shown here so a local or test environment with no mailer configured can
                 still complete the flow. --}}
            <div class="flex items-start gap-2.5 rounded-lg bg-canvas border border-line px-3.5 py-3 mb-4">
                <x-icon name="shield" class="w-4 h-4 text-ink-3 shrink-0 mt-0.5" />
                <p class="text-[12px] text-ink-2 leading-relaxed">
                    Test build — your code is
                    <span class="font-mono font-semibold text-ink tracking-wider">{{ session('debug_code') }}</span>.
                    This is never shown in production.
                </p>
            </div>
        @endif

        <form action="{{ route('password.verify.store') }}" method="POST" id="code-form">
            @csrf
            <input type="hidden" name="code" id="code">

            {{-- Capped width and centred: four boxes stretched across the full column
                 would read as four separate fields rather than one code. --}}
            <div class="flex items-center justify-center gap-2.5 mx-auto max-w-[248px]" id="code-boxes">
                @for($i = 0; $i < $length; $i++)
                    <input type="text" inputmode="numeric" autocomplete="one-time-code"
                           maxlength="1" aria-label="Digit {{ $i + 1 }}"
                           @if($i === 0) autofocus @endif
                           @class([
                               'w-14 h-14 text-center rounded-lg bg-panel border text-[22px] font-mono font-semibold text-ink',
                               'focus:outline-none focus:ring-[3px] transition-[border-color,box-shadow]',
                               'border-danger focus:border-danger focus:ring-danger-ring' => $errors->has('code'),
                               'border-line focus:border-primary focus:ring-primary-ring' => ! $errors->has('code'),
                           ])>
                @endfor
            </div>

            {{-- The fallback when the script is unavailable: the boxes above are then just
                 inert inputs, and this stays typeable so the step is still completable. --}}
            <noscript>
                <input type="text" name="code" inputmode="numeric" maxlength="{{ $length }}" required
                       placeholder="{{ $length }}-digit code"
                       class="w-full h-10 mt-3 px-3.5 rounded-lg bg-panel border border-line text-[13.5px] font-mono text-ink
                              focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
            </noscript>

            <x-button variant="gold" size="lg" tag="button" type="submit" class="w-full mt-5" icon-right="arrow-right">
                Verify code
            </x-button>
        </form>

        <form action="{{ route('password.resend') }}" method="POST" class="mt-4 text-center">
            @csrf
            <span class="text-[12.5px] text-ink-2">Didn't get it?</span>
            <button type="submit" class="text-[12.5px] font-medium text-primary-dark hover:underline ml-1">
                Send a new code
            </button>
        </form>

        <x-slot:footer>
            <a href="{{ route('password.request') }}"
               class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2 hover:text-ink transition-colors">
                <x-icon name="chevron-left" class="w-3.5 h-3.5" />
                Use a different email
            </a>
        </x-slot:footer>
    </x-auth.shell>

    <script>
        (function () {
            const boxes = Array.from(document.querySelectorAll('#code-boxes input'));
            const hidden = document.getElementById('code');
            const form = document.getElementById('code-form');

            const sync = () => { hidden.value = boxes.map(b => b.value).join(''); };

            boxes.forEach((box, i) => {
                box.addEventListener('input', () => {
                    // Strip anything non-numeric so a stray character can't sit in a box
                    // looking filled while the posted value fails the `digits:` rule.
                    box.value = box.value.replace(/\D/g, '').slice(0, 1);
                    sync();

                    if (box.value && i < boxes.length - 1) {
                        boxes[i + 1].focus();
                        boxes[i + 1].select();
                    }
                });

                box.addEventListener('keydown', (e) => {
                    // Backspace on an empty box steps back rather than doing nothing —
                    // otherwise correcting a typo means reaching for the mouse.
                    if (e.key === 'Backspace' && !box.value && i > 0) {
                        e.preventDefault();
                        boxes[i - 1].value = '';
                        boxes[i - 1].focus();
                        sync();
                    } else if (e.key === 'ArrowLeft' && i > 0) {
                        e.preventDefault();
                        boxes[i - 1].focus();
                    } else if (e.key === 'ArrowRight' && i < boxes.length - 1) {
                        e.preventDefault();
                        boxes[i + 1].focus();
                    }
                });

                box.addEventListener('focus', () => box.select());

                // A code copied out of the inbox lands as one string on whichever box has
                // focus; spread it across the remaining boxes instead of keeping just one digit.
                box.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const digits = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                    if (!digits) return;

                    digits.split('').slice(0, boxes.length - i).forEach((d, n) => {
                        boxes[i + n].value = d;
                    });
                    sync();
                    boxes[Math.min(i + digits.length, boxes.length - 1)].focus();
                });
            });

            form.addEventListener('submit', sync);
            sync();
        })();
    </script>
</x-layouts.guest>
