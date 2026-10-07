{{-- Ikon garis 24px untuk bagian "Kenapa kami". Dekoratif, jadi disembunyikan dari pembaca layar. --}}
<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('stock') <path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 12l9 4 9-4"/><path d="M3 17l9 4 9-4"/> @break
        @case('price') <path d="M3 12V4h8l9 9-8 8-9-9z"/><path d="M7.5 7.5h.01"/> @break
        @case('pay') <rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/> @break
        @case('pickup') <path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/> @break
        @case('safe') <path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/><path d="M8.5 12l2.5 2.5 4.5-5"/> @break
        @default <path d="M4 5h16v11H9l-5 4V5z"/><path d="M8 9.5h8"/><path d="M8 12.5h5"/>
    @endswitch
</svg>
