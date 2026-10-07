{{-- Logo toko. Pakai gambar sendiri bila STORE_LOGO diisi (mis. images/logo-klien.svg), kalau tidak pakai tanda bawaan yang warnanya ikut tema. --}}
@if (config('store.logo'))
    <img class="logo" src="{{ asset(config('store.logo')) }}" alt="" width="40" height="40" decoding="async">
@else
    <svg class="logo" viewBox="0 0 40 40" width="40" height="40" aria-hidden="true" focusable="false">
        <rect width="40" height="40" rx="11" fill="var(--tag)"/>
        <path d="M11 30 20 9l9 21M14.8 23.2h10.4" fill="none" stroke="#14231c" stroke-width="4.2" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="31.2" cy="8.8" r="3.4" fill="var(--brand)"/>
        <circle cx="31.2" cy="8.8" r="1.3" fill="var(--tag)"/>
    </svg>
@endif
