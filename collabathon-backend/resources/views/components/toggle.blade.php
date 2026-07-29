@props(['checked' => false, 'label' => null])

{{-- Track is a padded flex row; the knob is an in-flow child that slides.
     (Absolute + translate left the knob outside the track.) --}}
<button type="button"
        x-data="{ on: {{ $checked ? 'true' : 'false' }} }"
        @click="on = !on"
        role="switch"
        :aria-checked="on.toString()"
        @if($label) aria-label="{{ $label }}" @endif
        :class="on ? 'bg-primary' : 'bg-line'"
        {{ $attributes->merge(['class' => 'inline-flex items-center shrink-0 w-[38px] h-[21px] rounded-full px-[2px] cursor-pointer transition-colors duration-150']) }}>
    <span :class="on ? 'translate-x-[17px]' : 'translate-x-0'"
          class="block w-[17px] h-[17px] rounded-full bg-white shadow-sm transition-transform duration-150"></span>
</button>
