@props(['label', 'placeholder' => '', 'value' => '', 'type' => 'text'])

<div>
    <label class="block text-[12.5px] font-medium text-navy mb-1.5">{{ $label }}</label>
    <input type="{{ $type }}" placeholder="{{ $placeholder }}" value="{{ $value }}"
        class="w-full px-3.5 py-2.5 rounded-lg border border-border text-[13.5px] text-navy placeholder:text-text-muted focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
</div>
