@props(['icon', 'route', 'active' => false])

<a href="{{ $route }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-colors
    {{ $active ? 'bg-white/10 text-white' : 'text-white/55 hover:text-white/90 hover:bg-white/5' }}">
    <x-icon :name="$icon" class="w-[18px] h-[18px] {{ $active ? 'text-primary' : '' }}" />
    <span>{{ $slot }}</span>
    @if($active)
        <span class="ml-auto w-1.5 h-1.5 rounded-full bg-primary"></span>
    @endif
</a>
