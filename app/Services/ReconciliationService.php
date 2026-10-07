<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalLine;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\Qty;
use App\Support\Rupiah;

/**
 * Pemeriksaan silang supaya data yang tampil di web, desktop, dan Android bisa dipercaya.
 * Tidak mengubah data apa pun.
 */
class ReconciliationService
{
    /** @return array{ok: bool, checked_at: string, checks: array<int, array<string, mixed>>} */
    public function run(): array
    {
        $checks = [
            $this->stockCache(),
            $this->journalsBalanced(),
            $this->inventoryValue(),
            $this->salesVsOrders(),
            $this->receivableVsOrders(),
            $this->ordersHaveJournal(),
            $this->orderMath(),
        ];

        return [
            'ok' => collect($checks)->every(fn ($check) => $check['ok']),
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    /** Cache stock_qty harus sama dengan jumlah seluruh mutasi stok. */
    private function stockCache(): array
    {
        $sums = StockMovement::query()
            ->where(fn ($q) => $q->where('warehouse_id', Warehouse::mainId())->orWhereNull('warehouse_id'))
            ->selectRaw('product_id, SUM(qty_change) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $bad = [];
        foreach (Product::query()->orderBy('id')->get(['id', 'sku', 'name', 'stock_qty']) as $product) {
            $expected = Qty::toMilli($sums[$product->id] ?? 0);

            if ($product->qtyMilli() !== $expected) {
                $bad[] = [
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'cache' => Qty::pretty($product->stock_qty),
                    'from_movements' => Qty::pretty(Qty::fromMilli($expected)),
                ];
            }
        }

        // Gudang lain: saldo per gudang harus sama dengan jumlah mutasinya.
        $mainId = Warehouse::mainId();
        $other = StockMovement::query()->where('warehouse_id', '!=', $mainId)->whereNotNull('warehouse_id')
            ->selectRaw('product_id, warehouse_id, SUM(qty_change) as total')->groupBy('product_id', 'warehouse_id')->get()
            ->keyBy(fn ($r) => $r->product_id.'-'.$r->warehouse_id);
        $balances = \Illuminate\Support\Facades\DB::table('stock_balances')->get()->keyBy(fn ($r) => $r->product_id.'-'.$r->warehouse_id);
        foreach ($other->keys()->merge($balances->keys())->unique() as $key) {
            $a = Qty::toMilli($other[$key]->total ?? 0);
            $b = Qty::toMilli($balances[$key]->qty ?? 0);
            if ($a !== $b) {
                $bad[] = ['sku' => 'gudang '.$key, 'name' => 'saldo gudang lain', 'cache' => Qty::pretty(Qty::fromMilli($b)), 'from_movements' => Qty::pretty(Qty::fromMilli($a))];
            }
        }

        return $this->result('stock_cache', 'Stok produk = jumlah mutasi stok', $bad, 'Semua stok cocok dengan mutasinya.');
    }

    /** Setiap jurnal: total debit = total kredit, dan punya baris. */
    private function journalsBalanced(): array
    {
        $bad = JournalLine::query()
            ->selectRaw('journal_id, SUM(debit) as d, SUM(credit) as c')
            ->groupBy('journal_id')
            ->havingRaw('SUM(debit) <> SUM(credit)')
            ->get()
            ->map(fn ($row) => ['journal_id' => $row->journal_id, 'debit' => (int) $row->d, 'credit' => (int) $row->c])
            ->all();

        foreach (Journal::query()->doesntHave('lines')->pluck('id') as $id) {
            $bad[] = ['journal_id' => $id, 'debit' => 0, 'credit' => 0, 'note' => 'tanpa baris'];
        }

        return $this->result('journals_balanced', 'Semua jurnal seimbang (debit = kredit)', $bad, 'Seluruh jurnal seimbang.');
    }

    /** Saldo akun Persediaan vs nilai stok (stok x HPP). Toleransi kecil karena pembulatan HPP rata-rata. */
    private function inventoryValue(): array
    {
        $ledger = Account::netDebitByKey('inventory');
        $stock = (int) Product::query()->select(['id', 'stock_qty', 'cost'])->withOtherStock()->get()->sum(fn (Product $p) => $p->inventoryValue());
        $diff = $ledger - $stock;
        $tolerance = (int) config('store.reconcile_tolerance');

        $rows = abs($diff) > $tolerance
            ? [['account_balance' => Rupiah::format($ledger), 'stock_value' => Rupiah::format($stock), 'difference' => Rupiah::format($diff)]]
            : [];

        return $this->result(
            'inventory_value',
            'Akun Persediaan = nilai stok',
            $rows,
            'Selisih '.Rupiah::format($diff).' (dalam toleransi '.Rupiah::format($tolerance).').',
        );
    }

    /** Akun Penjualan harus sama dengan jumlah (subtotal - diskon) semua pesanan aktif. */
    private function salesVsOrders(): array
    {
        $ledger = -Account::netDebitByKey('sales');
        $orders = (int) Order::where('status', '!=', 'cancelled')->selectRaw('COALESCE(SUM(subtotal - discount_total), 0) as v')->value('v');

        $rows = $ledger !== $orders
            ? [['account_balance' => Rupiah::format($ledger), 'orders_total' => Rupiah::format($orders), 'difference' => Rupiah::format($ledger - $orders)]]
            : [];

        return $this->result('sales_vs_orders', 'Akun Penjualan = total pesanan aktif', $rows, 'Penjualan di jurnal sama dengan total pesanan.');
    }

    /** Akun Piutang harus sama dengan sisa tagihan semua pesanan aktif. */
    private function receivableVsOrders(): array
    {
        $ledger = Account::netDebitByKey('receivable');
        $orders = (int) Order::where('status', '!=', 'cancelled')->selectRaw('COALESCE(SUM(grand_total - paid_total - returned_total), 0) as v')->value('v');

        $rows = $ledger !== $orders
            ? [['account_balance' => Rupiah::format($ledger), 'orders_outstanding' => Rupiah::format($orders), 'difference' => Rupiah::format($ledger - $orders)]]
            : [];

        return $this->result('receivable_vs_orders', 'Akun Piutang = sisa tagihan pesanan', $rows, 'Piutang di jurnal sama dengan sisa tagihan pesanan.');
    }

    /** Pesanan aktif harus punya jurnal penjualan; pesanan batal tidak boleh punya jurnal aktif tersisa. */
    private function ordersHaveJournal(): array
    {
        $bad = [];

        $missing = Order::where('status', '!=', 'cancelled')
            ->whereDoesntHave('journals', fn ($q) => $q->where('type', 'sale'))
            ->limit(50)->pluck('number');
        foreach ($missing as $number) {
            $bad[] = ['order' => $number, 'problem' => 'tidak ada jurnal penjualan'];
        }

        $dangling = Order::where('status', 'cancelled')
            ->whereHas('journals', fn ($q) => $q->whereNull('reversal_of_id')->whereNull('reversed_by_id'))
            ->limit(50)->pluck('number');
        foreach ($dangling as $number) {
            $bad[] = ['order' => $number, 'problem' => 'dibatalkan tapi jurnal belum dibalik'];
        }

        return $this->result('orders_have_journal', 'Setiap pesanan punya jurnal yang sesuai statusnya', $bad, 'Semua pesanan punya jurnal yang benar.');
    }

    /** grand_total = subtotal - diskon + pajak + ongkir; terbayar tidak melebihi total. */
    private function orderMath(): array
    {
        $bad = Order::query()
            ->whereRaw('grand_total <> subtotal - discount_total + tax_total + shipping_fee')
            ->orWhereRaw('paid_total + returned_total > grand_total')
            ->limit(50)->get(['number', 'subtotal', 'discount_total', 'tax_total', 'shipping_fee', 'grand_total', 'paid_total'])
            ->map(fn ($o) => ['order' => $o->number, 'grand_total' => $o->grand_total, 'paid_total' => $o->paid_total])
            ->all();

        return $this->result('order_math', 'Hitungan total setiap pesanan benar', $bad, 'Semua total pesanan konsisten.');
    }

    private function result(string $key, string $title, array $rows, string $okMessage): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'ok' => $rows === [],
            'summary' => $rows === [] ? $okMessage : count($rows).' data bermasalah.',
            'rows' => array_slice($rows, 0, 50),
        ];
    }
}
