@props(['name', 'value' => 0, 'min' => 0, 'max' => 65535, 'form' => null, 'inputId' => null])

{{--
    A plain <input type="number">'s native up/down spinner is inconsistent across
    browsers — always visible in Chrome/Firefox, hidden until hover in Safari on
    macOS (not something CSS can override; it's OS-level chrome, not stylable), which
    is what made this "Order" column look broken half the time. Hiding the native one
    and drawing a fixed pair of buttons instead means it looks and behaves the same
    everywhere, always visible, never an empty-looking box.

    stepUp()/stepDown() are the browser's own number-input methods — they already
    respect this input's min/max/step, so there's no manual clamping to get wrong here.
    The dispatched 'input' event is what makes the bump actually register as a change
    for anything (validation, a dirty-state flag) that listens for one.
--}}
<div class="relative w-full">
    <input id="{{ $inputId ?? $name }}" @if($form) form="{{ $form }}" @endif
           name="{{ $name }}" type="number" min="{{ $min }}" max="{{ $max }}" value="{{ $value }}"
           {{ $attributes->merge(['class' => 'w-full h-9 pl-2 pr-6 rounded-lg bg-panel border border-line text-[13px] text-ink nums
                  focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring transition-[border-color,box-shadow]
                  [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none']) }}>

    <div class="absolute right-0 top-0 bottom-0 flex flex-col w-6">
        <button type="button" tabindex="-1" aria-label="Increase"
                onclick="const i=this.closest('.relative').querySelector('input'); i.stepUp(); i.dispatchEvent(new Event('input', {bubbles: true}))"
                class="flex-1 flex items-center justify-center text-ink-3 hover:text-ink hover:bg-canvas rounded-tr-lg transition-colors">
            <span class="block w-0 h-0 border-x-[3.5px] border-x-transparent border-b-[4.5px] border-b-current"></span>
        </button>
        <button type="button" tabindex="-1" aria-label="Decrease"
                onclick="const i=this.closest('.relative').querySelector('input'); i.stepDown(); i.dispatchEvent(new Event('input', {bubbles: true}))"
                class="flex-1 flex items-center justify-center text-ink-3 hover:text-ink hover:bg-canvas rounded-br-lg transition-colors">
            <span class="block w-0 h-0 border-x-[3.5px] border-x-transparent border-t-[4.5px] border-t-current"></span>
        </button>
    </div>
</div>
