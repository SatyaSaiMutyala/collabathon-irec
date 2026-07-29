{{-- Session status + validation errors, shown once above page content. --}}

@if(session('status'))
    <div x-data="{ show: true }" x-show="show" x-transition
         class="flex items-start gap-3 rounded-xl bg-success-soft ring-1 ring-inset ring-success-ring px-4 py-3 mb-4">
        <x-icon name="check" class="w-4 h-4 text-success shrink-0 mt-0.5" />
        <p class="text-[12.5px] text-ink-2 leading-relaxed flex-1">{{ session('status') }}</p>
        <button type="button" @click="show = false" aria-label="Dismiss"
                class="text-ink-3 hover:text-ink shrink-0">
            <x-icon name="x" class="w-3.5 h-3.5" />
        </button>
    </div>
@endif

@if($errors->any())
    <div class="flex items-start gap-3 rounded-xl bg-danger-soft ring-1 ring-inset ring-danger-ring px-4 py-3 mb-4">
        <x-icon name="x" class="w-4 h-4 text-danger shrink-0 mt-0.5" />
        <div class="text-[12.5px] text-ink-2 leading-relaxed">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    </div>
@endif
