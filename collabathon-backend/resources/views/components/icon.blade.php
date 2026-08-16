@props(['name', 'class' => 'w-5 h-5'])

@php
$paths = [
    'dashboard' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
    'users' => '<circle cx="9" cy="8" r="3"/><path d="M3.5 19c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/><circle cx="17" cy="9" r="2.3"/><path d="M15 19c0-2.3 1.6-4.1 4-4.6"/>',
    'building' => '<rect x="4" y="3" width="12" height="18" rx="1"/><path d="M16 21v-8l4 2v6"/><path d="M7.5 7h1.5M11 7h1.5M7.5 10.5h1.5M11 10.5h1.5M7.5 14h1.5M11 14h1.5"/>',
    'chart' => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M20 20v-3"/>',
    'cog' => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.2M12 18.8V21M4.9 6.6l1.6 1.6M17.5 15.8l1.6 1.6M3 12h2.2M18.8 12H21M4.9 17.4l1.6-1.6M17.5 8.2l1.6-1.6"/>',
    'bell' => '<path d="M6 9a6 6 0 1 1 12 0c0 4 1.5 5.5 1.5 5.5h-15S6 13 6 9Z"/><path d="M10 18a2 2 0 0 0 4 0"/>',
    'logout' => '<path d="M9 20H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h4"/><path d="M15 16l4-4-4-4"/><path d="M19 12H9"/>',
    'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M20 20l-4.8-4.8"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
    'check' => '<path d="M5 12.5l4.5 4.5L19 7"/>',
    'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
    'eye' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.6"/>',
    'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
    'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
    'chevron-right' => '<path d="M9 6l6 6-6 6"/>',
    'chevron-left' => '<path d="M15 6l-6 6 6 6"/>',
    'chevron-up-down' => '<path d="M8 10l4-4 4 4"/><path d="M16 14l-4 4-4-4"/>',
    'mail' => '<rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="M3.5 6l8.5 7 8.5-7"/>',
    'phone' => '<path d="M6.5 4.5c-1.2 0-2 .9-1.9 2 .5 5.5 5.4 10.4 10.9 10.9 1.1.1 2-.7 2-1.9v-1.8a1 1 0 0 0-.8-1L14 12l-1.7 1.7a10 10 0 0 1-4-4L10 8 8.3 5.3a1 1 0 0 0-1-.8H6.5Z"/>',
    'palette' => '<path d="M12 3a9 9 0 1 0 0 18c1.2 0 1.8-1 1-1.8-.6-.6-.3-1.7.6-1.9 3.7-.8 6.4-3.4 6.4-6.8C20 6.6 16.4 3 12 3Z"/><circle cx="8" cy="10" r="1.1"/><circle cx="12" cy="7.5" r="1.1"/><circle cx="16" cy="10" r="1.1"/><circle cx="9.5" cy="14.5" r="1.1"/>',
    'list' => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/>',
    'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    'lock' => '<rect x="5" y="10.5" width="14" height="9" rx="1.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
    'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
    'arrow-down' => '<path d="M12 5v14M6 13l6 6 6-6"/>',
    'filter' => '<path d="M4 5h16l-6.4 7.4V19l-3.2-1.8v-4.8L4 5Z"/>',
    'download' => '<path d="M12 4v10"/><path d="M8 11l4 4 4-4"/><path d="M5 19h14"/>',
    'upload' => '<path d="M12 20V10"/><path d="M8 13l4-4 4 4"/><path d="M5 19h14"/>',
    'dots' => '<circle cx="12" cy="6" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="12" cy="18" r="1.4"/>',
    'external' => '<path d="M14 4h6v6"/><path d="M20 4l-8.5 8.5"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
    'shield' => '<path d="M12 3l7 3v5.5c0 4.2-2.9 7.8-7 9-4.1-1.2-7-4.8-7-9V6l7-3Z"/><path d="M9 12l2 2 4-4"/>',
    'inbox' => '<path d="M4 13h4l1.5 2.5h5L16 13h4"/><path d="M4.5 13 6.8 5.6A1 1 0 0 1 7.8 5h8.4a1 1 0 0 1 1 .7L19.5 13v5a1 1 0 0 1-1 1h-13a1 1 0 0 1-1-1v-5Z"/>',
    // A spreadsheet grid and a text document — the two export formats. `list` and
    // `external` stood in for them and read as "a list" and "a link", which is not what
    // an Excel/PDF choice is asking.
    'sheet' => '<rect x="3.5" y="4.5" width="17" height="15" rx="1.5"/><path d="M3.5 9.5h17M3.5 14.5h17M9.5 9.5v10"/>',
    'file-text' => '<path d="M13.5 3.5H7A1.5 1.5 0 0 0 5.5 5v14A1.5 1.5 0 0 0 7 20.5h10a1.5 1.5 0 0 0 1.5-1.5V8.5l-5-5Z"/><path d="M13.5 3.5V8a.5.5 0 0 0 .5.5h4.5"/><path d="M8.75 13h6.5M8.75 16.25h4"/>',
    'map-pin' => '<path d="M12 21s7-5.3 7-11a7 7 0 1 0-14 0c0 5.7 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>',
    'sparkles' => '<path d="M12 4l1.6 4.4L18 10l-4.4 1.6L12 16l-1.6-4.4L6 10l4.4-1.6L12 4Z"/><path d="M18.5 15.5l.7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7.7-1.8Z"/>',
    'trending-up' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
    'user-check' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5c0-3.1 2.5-5.5 5.5-5.5s5.5 2.4 5.5 5.5"/><path d="M16 13.5l1.8 1.8 3.7-3.7"/>',
];
$path = $paths[$name] ?? '';
@endphp

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="{{ $class }}" aria-hidden="true">
    {!! $path !!}
</svg>
