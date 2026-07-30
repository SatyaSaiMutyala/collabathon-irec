@props([
    'name' => 'password',
    'label' => 'Temporary password',
    'hint' => 'Shown once more after saving so you can share it.',
    'placeholder' => 'At least 8 characters',
    'prefill' => true,   // create forms start with a suggestion; reset starts blank
    'required' => false,
])

{{--
    Password input with a show/hide eye and a "Generate one" link.

    Used by the developer + team create forms and the reset-password dialog, so the
    generate/reveal behaviour stays identical everywhere. Leaving it blank is allowed
    where the controller falls back to a generated password.
--}}

@php
    $id = $name;
    $hasError = $errors->has($id);
@endphp

<div x-data="passwordField(@js(old($id)), @js((bool) $prefill))" {{ $attributes->only('class') }}>
    <div class="flex items-center justify-between gap-3 mb-1.5">
        <label for="{{ $id }}" class="flex items-center gap-1 text-[12.5px] font-medium text-ink">
            {{ $label }}
            @if($required)<span class="text-danger" aria-hidden="true">*</span>@endif
        </label>
        <button type="button" @click="regenerate()"
                class="text-[11.5px] font-medium text-primary-dark hover:underline">
            Generate one
        </button>
    </div>

    <div class="relative">
        {{-- Alpine owns `type` so the value survives toggling between text and password. --}}
        <input id="{{ $id }}" name="{{ $id }}" x-model="password"
               :type="reveal ? 'text' : 'password'" type="password"
               placeholder="{{ $placeholder }}"
               minlength="8" maxlength="72" autocomplete="new-password"
               @if($required) required @endif
               @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
               @class([
                   'w-full h-10 pl-3.5 pr-10 rounded-lg bg-panel border text-[13.5px] text-ink font-mono',
                   'placeholder:font-sans placeholder:text-ink-3',
                   'focus:outline-none focus:ring-[3px] transition-[border-color,box-shadow]',
                   'border-danger focus:border-danger focus:ring-danger-ring' => $hasError,
                   'border-line focus:border-primary focus:ring-primary-ring' => ! $hasError,
               ])>

        <button type="button" @click="reveal = ! reveal" tabindex="-1"
                :aria-label="reveal ? 'Hide password' : 'Show password'"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-3 hover:text-ink p-1.5 rounded-md transition-colors">
            <x-icon name="eye" class="w-4 h-4" x-show="! reveal" />
            <x-icon name="x" class="w-4 h-4" x-show="reveal" x-cloak />
        </button>
    </div>

    @error($id)
        <p id="{{ $id }}-error" class="text-[11.5px] text-danger mt-1.5">{{ $message }}</p>
    @elseif($hint)
        <p class="text-[11.5px] text-ink-3 mt-1.5">{{ $hint }}</p>
    @enderror
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('passwordField', (previous, prefill) => ({
                    // `previous` keeps what was typed when validation bounced the form back.
                    password: previous || (prefill ? window.makePassword() : ''),
                    reveal: false,

                    regenerate() {
                        this.password = window.makePassword();
                        this.reveal = true;
                    },
                }));
            });
        </script>
    @endpush
@endonce
