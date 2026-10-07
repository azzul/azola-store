@php
    $hex = fn ($v, $f) => preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $v) ? $v : $f;
    $brand = $hex(config('store.theme.brand'), '#0F5C46');
    $accent = $hex(config('store.theme.accent'), '#FFD43B');
    $unread = \App\Models\ContactMessage::whereNull('read_at')->count();
    $pendingReviews = \App\Models\Testimonial::where('is_published', false)->count();
    $count = fn ($stage) => \App\Models\Order::where('channel', 'web')->where('fulfillment', $stage)->count();
    $openPo = null;
    // Menu bergrup. Entri: [nama route, label, pola URL untuk penanda aktif, lencana opsional]
    $groups = [
        ['Ringkasan', [
            ['admin.dashboard', 'Dasbor', 'admin'],
        ]],
        ['Master', [
            ['admin.products.index', 'Produk (SKU)', 'admin/produk*'],
            ['admin.catalog.index', 'Katalog toko & foto', 'admin/katalog*'],
            ['admin.categories.index', 'Kategori', 'admin/kategori*'],
            ['admin.variations.index', 'Variasi', 'admin/variasi*'],
            ['admin.units.index', 'Satuan', 'admin/satuan*'],
            ['admin.conversions.index', 'Konversi satuan', 'admin/konversi-satuan*'],
            ['admin.prices.index', 'Harga', 'admin/harga*'],
            ['admin.accounts.index', 'Akun (COA)', 'admin/akun*'],
            ['admin.suppliers.index', 'Supplier', 'admin/supplier*'],
            ['admin.customers.index', 'Customer', 'admin/customer*'],
            ['admin.warehouses.index', 'Gudang', 'admin/gudang*'],
            ['admin.etalases.index', 'Etalase', 'admin/etalase*'],
        ]],
        ['Pembelian', [
            ['admin.purchases.index', 'Laporan pembelian', 'admin/pembelian*'],
            ['admin.purchases.create', 'Input pembelian', 'admin/pembelian/baru'],
            ['admin.transfers.index', 'Alih gudang & penerimaan', 'admin/alih-gudang*'],
            ['admin.purchase-returns.index', 'Retur pembelian', 'admin/retur-pembelian*'],
            ['admin.payables.index', 'Hutang', 'admin/hutang*'],
            ['admin.supplier-payments.index', 'Pembayaran hutang', 'admin/bayar-hutang*'],
        ]],
        ['Penjualan', [
            ['admin.sales.pos', 'Penjualan kasir', 'admin/penjualan/kasir*'],
            ['admin.sales.index', 'Daftar penjualan', 'admin/penjualan'],
            ['admin.sales.create', 'Input penjualan', 'admin/penjualan/baru'],
            ['admin.sales.cancelled', 'Penjualan batal', 'admin/penjualan/batal*'],
            ['admin.sales.items', 'Penjualan detail (item)', 'admin/penjualan/item*'],
            ['admin.sale-returns.index', 'Retur penjualan', 'admin/retur-penjualan*'],
            ['admin.overpayments.index', 'Kembalian lebih transfer', 'admin/kembalian-lebih*'],
            ['admin.orders.index', 'Semua pesanan', 'admin/pesanan*'],
        ]],
        ['Order online', [
            ['admin.online.new', 'Order baru', 'admin/order-online/baru*', $count('new')],
            ['admin.online.process', 'Perlu proses', 'admin/order-online/proses*', $count('process')],
            ['admin.online.shipped', 'Sedang dikirim', 'admin/order-online/dikirim*', $count('shipped')],
            ['admin.online.done', 'Selesai', 'admin/order-online/selesai*'],
            ['admin.online.cancelled', 'Batal', 'admin/order-online/batal*'],
        ]],
        ['Keuangan', [
            ['admin.deposits.index', 'DP customer', 'admin/dp-customer*'],
            ['admin.supplier-receivables.index', 'Piutang supplier', 'admin/piutang-supplier*'],
            ['admin.cards.payable', 'Kartu hutang', 'admin/kartu-hutang*'],
            ['admin.cards.receivable', 'Kartu piutang', 'admin/kartu-piutang*'],
            ['admin.recap.index', 'Rekap pendapatan', 'admin/rekap-pendapatan*'],
            ['admin.cash.index', 'Rekap kas harian', 'admin/kas-harian*'],
            ['admin.balances.index', 'Mutasi saldo', 'admin/mutasi-saldo*'],
            ['admin.assets.index', 'Aset & depresiasi', 'admin/aset*'],
            ['admin.expenses.index', 'Biaya & kas masuk/keluar', 'admin/biaya*'],
        ]],
        ['Stok', [
            ['admin.stock', 'Stok barang terkini', 'admin/stok'],
            ['admin.stock.mutation', 'Mutasi stok', 'admin/mutasi-stok*'],
            ['admin.stock.card', 'Kartu stok', 'admin/kartu-stok*'],
            ['admin.adjustments.index', 'Penyesuaian stok', 'admin/penyesuaian-stok*'],
            ['admin.opnames.create', 'Stok opname', 'admin/opname/baru'],
            ['admin.opnames.index', 'Data stok opname', 'admin/opname'],
        ]],
        ['Laporan', [
            ['admin.journals.index', 'Jurnal umum', 'admin/jurnal'],
            ['admin.reports.ledger', 'General ledger', 'admin/buku-besar*'],
            ['admin.reports.income', 'Jurnal laba rugi', 'admin/laba-rugi*'],
            ['admin.reports.balance', 'Jurnal neraca', 'admin/neraca'],
            ['admin.reports.trial', 'Neraca saldo', 'admin/neraca-saldo*'],
            ['admin.adjustment-journals.index', 'Jurnal penyesuaian', 'admin/jurnal-penyesuaian*'],
            ['admin.reconcile', 'Rekonsiliasi', 'admin/rekonsiliasi*'],
        ]],
        ['Konten toko', [
            ['admin.articles.index', 'Artikel', 'admin/artikel*'],
            ['admin.messages.index', 'Pesan masuk', 'admin/pesan*', $unread],
            ['admin.reviews.index', 'Ulasan', 'admin/ulasan*', $pendingReviews],
            ['admin.clients.index', 'Klien', 'admin/klien*'],
        ]],
        ['Sistem', [
            ['admin.devices.index', 'Perangkat POS', 'admin/perangkat*'],
            ['admin.settings.index', 'Pengaturan toko', 'admin/pengaturan*'],
        ]],
    ];
    // Hanya tampilkan menu yang route-nya ada; grup yang sedang dibuka otomatis terbuka.
    $groups = collect($groups)->map(fn ($g) => [$g[0], collect($g[1])->filter(fn ($i) => \Illuminate\Support\Facades\Route::has($i[0]))->values()->all()])
        ->filter(fn ($g) => $g[1] !== [])->values()->all();
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Admin') - {{ config('store.name') }}</title>
    <style>:root{--brand:{{ $brand }};--tag:{{ $accent }}}</style>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) }}">
</head>
<body>
<div class="shell">
    <aside class="side">
        <a class="side__brand" href="{{ route('admin.dashboard') }}">{{ config('store.name') }}<small>Admin</small></a>
        <nav aria-label="Menu admin">
            @foreach ($groups as [$title, $items])
                @php($on = collect($items)->contains(fn ($i) => request()->is($i[2])))
                <details class="navgroup" @if ($on || $title === 'Ringkasan') open @endif data-group="{{ $title }}">
                    <summary>{{ $title }}@php($sum = collect($items)->sum(fn ($i) => (int) ($i[3] ?? 0)))@if ($sum)<span class="navcount">{{ $sum }}</span>@endif</summary>
                    @foreach ($items as $i)
                        <a href="{{ route($i[0]) }}" @class(['is-on' => request()->is($i[2])])>{{ $i[1] }}@if (! empty($i[3]))<span class="navcount">{{ $i[3] }}</span>@endif</a>
                    @endforeach
                </details>
            @endforeach
        </nav>
        <div class="side__foot">
            <a href="{{ route('home') }}" target="_blank" rel="noopener">Lihat toko</a>
            <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="linkbtn">Keluar ({{ auth()->user()->name }})</button></form>
        </div>
    </aside>

    <main class="main" id="isi">
        <header class="head">
            <h1>@yield('heading')</h1>
            <div class="head__actions">@yield('actions')</div>
        </header>

        @if (session('ok'))<div class="flash flash--ok" role="status">{{ session('ok') }}</div>@endif
        @if ($errors->any())
            <div class="flash flash--err" role="alert">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        @yield('content')
    </main>
</div>
<script>
    // Ingat grup menu yang dibuka (hanya kenyamanan; aman bila penyimpanan diblokir).
    (function () {
        try {
            var saved = JSON.parse(localStorage.getItem('admin.nav') || '{}');
            document.querySelectorAll('.navgroup').forEach(function (d) {
                var k = d.dataset.group;
                if (!d.querySelector('.is-on') && k in saved) d.open = saved[k];
                d.addEventListener('toggle', function () { saved[k] = d.open; try { localStorage.setItem('admin.nav', JSON.stringify(saved)); } catch (e) {} });
            });
            var on = document.querySelector('.side .is-on'); if (on && on.scrollIntoView) on.scrollIntoView({ block: 'nearest' });
        } catch (e) {}
    })();
</script>
<script src="{{ asset('js/lines.js') }}?v={{ @filemtime(public_path('js/lines.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
