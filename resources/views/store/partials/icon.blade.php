{{-- Ikon garis 24px. Dekoratif: selalu aria-hidden, label ada di teks atau aria-label tautannya. --}}
@php $size = $size ?? 26; @endphp
<svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('stock') <path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 12l9 4 9-4"/><path d="M3 17l9 4 9-4"/> @break
        @case('price') <path d="M3 12V4h8l9 9-8 8-9-9z"/><path d="M7.5 7.5h.01"/> @break
        @case('pay') <rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/> @break
        @case('pickup') <path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/> @break
        @case('safe') <path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/><path d="M8.5 12l2.5 2.5 4.5-5"/> @break
        @case('help') <path d="M4 5h16v11H9l-5 4V5z"/><path d="M8 9.5h8"/><path d="M8 12.5h5"/> @break
        @case('cart') <path d="M3 4h2.2l2.3 11h10.2L20 8H6.3"/><circle cx="9" cy="19" r="1.4"/><circle cx="17" cy="19" r="1.4"/> @break
        @case('user') <circle cx="12" cy="8" r="4"/><path d="M4 21c.9-4.2 4-6.2 8-6.2s7.1 2 8 6.2"/> @break
        @case('receipt') <path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z"/><path d="M9 8h6M9 12h6"/> @break
        @case('home') <path d="M4 11l8-7 8 7v9H4z"/><path d="M10 20v-6h4v6"/> @break
        @case('grid') <rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/> @break
        @case('wa') <path d="M20 11.5A8.5 8.5 0 0 1 7.6 19L4 20l1.1-3.4A8.5 8.5 0 1 1 20 11.5z"/><path d="M9.2 8.6c.3 2.2 2.3 4.3 4.7 5.2.5.2 1 0 1.3-.4l.5-.7-1.9-1.1-.7.6c-.8-.3-1.5-1-1.9-1.8l.6-.8L10.7 7l-.8.6c-.5.4-.8.7-.7 1z"/> @break
        @case('instagram') <rect x="4" y="4" width="16" height="16" rx="4.5"/><circle cx="12" cy="12" r="3.7"/><path d="M16.8 7.3h.01"/> @break
        @case('facebook') <path d="M14.5 8H17V4.6h-2.7A3.8 3.8 0 0 0 10.5 8.4V11H8v3.4h2.5V21H14v-6.6h2.5l.5-3.4H14V8.9c0-.6.3-.9.5-.9z"/> @break
        @case('tiktok') <path d="M14 4v10.2a3.6 3.6 0 1 1-3.6-3.6"/><path d="M14 4c.3 2.4 1.9 3.9 4.2 4.1"/> @break
        @case('youtube') <rect x="3" y="6" width="18" height="12" rx="3.5"/><path d="M10.5 9.5v5l4.3-2.5-4.3-2.5z"/> @break
        @case('mail') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/> @break
        @case('pin') <path d="M12 21s6-5.6 6-11a6 6 0 1 0-12 0c0 5.4 6 11 6 11z"/><circle cx="12" cy="10" r="2.2"/> @break
        @case('phone') <path d="M5 4h4l1.6 4-2.3 1.4a11 11 0 0 0 5.3 5.3L15 12.4l4 1.6v4a2 2 0 0 1-2 2A14 14 0 0 1 3 6a2 2 0 0 1 2-2z"/> @break
        @case('search') <circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/> @break
        @case('menu') <path d="M4 7h16M4 12h16M4 17h16"/> @break
        @case('download') <path d="M12 4v11"/><path d="M7.5 11 12 15.5 16.5 11"/><path d="M5 20h14"/> @break
        @case('file') <path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4"/><path d="M9.5 12h5M9.5 16h5"/> @break
        @case('arrow') <path d="M5 12h14M13 6l6 6-6 6"/> @break
        @default <circle cx="12" cy="12" r="8"/>
    @endswitch
</svg>
