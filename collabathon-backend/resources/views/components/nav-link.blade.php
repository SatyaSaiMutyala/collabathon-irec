@props(['icon', 'route', 'active' => false, 'count' => null])

<a href="{{ $route }}"
   @if($active) aria-current="page" @endif
   class="group relative flex items-center gap-3 pl-3.5 pr-3 py-2 rounded-lg text-[13px] font-medium transition-colors
   {{ $active ? 'bg-white/[0.09] text-white' : 'text-white/55 hover:text-white/90 hover:bg-white/[0.05]' }}">

    {{-- Active rail: a shape channel, so the state isn't carried by colour alone --}}
    <span class="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] rounded-r-full bg-primary transition-all duration-150
        {{ $active ? 'h-4 opacity-100' : 'h-0 opacity-0' }}"></span>

    <x-icon :name="$icon" class="w-[17px] h-[17px] shrink-0 {{ $active ? 'text-primary' : 'text-white/45 group-hover:text-white/70' }} transition-colors" />
    <span class="truncate">{{ $slot }}</span>

    @if($count)
        <span class="ml-auto min-w-[19px] h-[19px] px-1.5 inline-flex items-center justify-center rounded-full
                     bg-primary text-navy text-[10.5px] font-semibold nums shrink-0">{{ $count }}</span>
    @endif
</a>
