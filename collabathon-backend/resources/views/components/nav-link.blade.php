@props(['icon', 'route', 'active' => false, 'count' => null])

<a href="{{ $route }}"
   @if($active) aria-current="page" @endif
   class="group relative flex items-center gap-3 pl-3.5 pr-3 py-2.5 rounded-xl text-[13px] font-medium transition-colors
   {{ $active ? 'bg-sidebar-active text-primary-dark' : 'text-sidebar-text-2 hover:text-sidebar-text hover:bg-sidebar-hover' }}">

    <x-icon :name="$icon" class="w-[17px] h-[17px] shrink-0 {{ $active ? 'text-primary' : 'text-sidebar-text-2 group-hover:text-sidebar-text' }} transition-colors" />
    <span class="truncate">{{ $slot }}</span>

    @if($count)
        <span class="ml-auto min-w-[19px] h-[19px] px-1.5 inline-flex items-center justify-center rounded-badge
                     {{ $active ? 'bg-primary text-white' : 'bg-primary/12 text-primary-dark' }} text-[10.5px] font-semibold nums shrink-0">{{ $count }}</span>
    @endif
</a>
