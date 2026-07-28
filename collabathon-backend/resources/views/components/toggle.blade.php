@props(['checked' => false])

<button type="button" x-data="{ on: {{ $checked ? 'true' : 'false' }} }" @click="on = !on"
    :class="on ? 'bg-primary' : 'bg-border'"
    class="relative w-10 h-[22px] rounded-full transition-colors shrink-0">
    <span :class="on ? 'translate-x-[20px]' : 'translate-x-[2px]'"
        class="absolute top-[2px] w-[18px] h-[18px] rounded-full bg-white shadow transition-transform"></span>
</button>
