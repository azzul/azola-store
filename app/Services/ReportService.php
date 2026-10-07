<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerDeposit;
use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Warehouse;
use App\Support\Qty;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Laporan keuangan dan stok. Hanya membaca; semua angka berasal dari jurnal dan mutasi stok. */
class ReportService
{
    /** Saldo menurut sisi normal akun (aset/beban: debit - kredit, lainnya: kredit - debit). */
    private function signed(string $normal, int $debit, int $credit): int
    {
        return $normal === 'debit' ? $debit - $credit : $credit - $debit;
    }

    /** @return Collection<int, object{account_id: int, debit: int, credit: int}> */
    private function sums(?string $from, ?string $to, bool $before = false): Collection
    {
        $query = DB::table('journal_lines')->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->selectRaw('journal_lines.account_id, SUM(journal_lines.debit) as debit, SUM(journal_lines.credit) as credit')
            ->groupBy('journal_lines.account_id');

        if ($before) {
            $query->whereDate('journals.date', '<', $from);
        } else {
            $from && $query->whereDate('journals.date', '>=', $from);
            $to && $query->whereDate('journals.date', '<=', $to);
        }

        return $query->get()->keyBy('account_id');
    }

    /** Neraca saldo + mutasi: saldo awal, debit, kredit, saldo akhir per akun (dalam sisi normal akun). */
    public function trial(string $from, string $to, ?array $types = null): array
    {
        $opening = $this->sums($from, null, true);
        $period = $this->sums($from, $to);
        $rows = [];

        foreach (Account::when($types, fn ($q) => $q->whereIn('type', $types))->orderBy('code')->get() as $account) {
            $o = $opening[$account->id] ?? null;
            $p = $period[$account->id] ?? null;
            $open = $this->signed($account->normal_balance, (int) ($o->debit ?? 0), (int) ($o->credit ?? 0));
            $debit = (int) ($p->debit ?? 0);
            $credit = (int) ($p->credit ?? 0);

            if ($open === 0 && $debit === 0 && $credit === 0) {
                continue;
            }

            $rows[] = [
                'account' => $account, 'opening' => $open, 'debit' => $debit, 'credit' => $credit,
                'closing' => $open + $this->signed($account->normal_balance, $debit, $credit),
            ];
        }

        return [
            'rows' => $rows,
            'debit' => array_sum(array_column($rows, 'debit')),
            'credit' => array_sum(array_column($rows, 'credit')),
        ];
    }

    /** Buku besar satu akun: saldo awal, baris berurutan dengan saldo berjalan, dan saldo akhir. */
    public function ledger(Account $account, string $from, string $to): array
    {
        $before = $this->sums($from, null, true)[$account->id] ?? null;
        $opening = $this->signed($account->normal_balance, (int) ($before->debit ?? 0), (int) ($before->credit ?? 0));

        $lines = DB::table('journal_lines')->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->where('journal_lines.account_id', $account->id)
            ->whereDate('journals.date', '>=', $from)->whereDate('journals.date', '<=', $to)
            ->orderBy('journals.date')->orderBy('journals.id')->orderBy('journal_lines.id')
            ->get(['journals.id as journal_id', 'journals.number', 'journals.date', 'journals.description', 'journal_lines.debit', 'journal_lines.credit', 'journal_lines.memo']);

        $balance = $opening;
        $rows = [];
        $debit = 0;
        $credit = 0;
        foreach ($lines as $line) {
            $balance += $this->signed($account->normal_balance, (int) $line->debit, (int) $line->credit);
            $debit += (int) $line->debit;
            $credit += (int) $line->credit;
            $rows[] = ['line' => $line, 'balance' => $balance];
        }

        return ['opening' => $opening, 'rows' => $rows, 'debit' => $debit, 'credit' => $credit, 'closing' => $balance];
    }

    /** @return array{revenue: array, cogs: array, expense: array, totals: array<string, int>} */
    public function incomeStatement(string $from, string $to): array
    {
        $period = $this->sums($from, $to);
        $groups = ['revenue' => [], 'cogs' => [], 'expense' => []];

        foreach (Account::whereIn('type', array_keys($groups))->orderBy('code')->get() as $account) {
            $p = $period[$account->id] ?? null;
            $debit = (int) ($p->debit ?? 0);
            $credit = (int) ($p->credit ?? 0);
            if ($debit === 0 && $credit === 0) {
                continue;
            }
            // Pendapatan: kredit - debit; HPP & beban: debit - kredit.
            $groups[$account->type][] = ['account' => $account, 'amount' => $account->type === 'revenue' ? $credit - $debit : $debit - $credit];
        }

        $revenue = array_sum(array_column($groups['revenue'], 'amount'));
        $cogs = array_sum(array_column($groups['cogs'], 'amount'));
        $expense = array_sum(array_column($groups['expense'], 'amount'));

        return $groups + ['totals' => [
            'revenue' => $revenue, 'cogs' => $cogs, 'gross' => $revenue - $cogs,
            'expense' => $expense, 'net' => $revenue - $cogs - $expense,
        ]];
    }

    /** Neraca per tanggal. Laba berjalan (semua pendapatan - HPP - beban sampai tanggal itu) masuk ke ekuitas. */
    public function balanceSheet(string $asOf): array
    {
        $sums = $this->sums(null, $asOf);
        $sections = ['asset' => [], 'liability' => [], 'equity' => []];
        $earnings = 0;

        foreach (Account::orderBy('code')->get() as $account) {
            $p = $sums[$account->id] ?? null;
            $debit = (int) ($p->debit ?? 0);
            $credit = (int) ($p->credit ?? 0);

            if (in_array($account->type, ['revenue'], true)) {
                $earnings += $credit - $debit;
            } elseif (in_array($account->type, ['cogs', 'expense'], true)) {
                $earnings -= $debit - $credit;
            } elseif ($debit !== 0 || $credit !== 0) {
                $sections[$account->type][] = ['account' => $account, 'amount' => $this->signed($account->normal_balance, $debit, $credit)];
            }
        }

        $assets = array_sum(array_column($sections['asset'], 'amount'));
        $liabilities = array_sum(array_column($sections['liability'], 'amount'));
        $equity = array_sum(array_column($sections['equity'], 'amount'));

        return $sections + [
            'earnings' => $earnings,
            'totals' => [
                'asset' => $assets, 'liability' => $liabilities, 'equity' => $equity + $earnings,
                'balanced' => $assets === $liabilities + $equity + $earnings,
            ],
        ];
    }

    // ------------------------------------------------------------------- Stok

    private function warehouseFilter($query, ?int $warehouseId)
    {
        if (! $warehouseId) {
            return $query;
        }

        return $warehouseId === Warehouse::mainId()
            ? $query->where(fn ($q) => $q->where('stock_movements.warehouse_id', $warehouseId)->orWhereNull('stock_movements.warehouse_id'))
            : $query->where('stock_movements.warehouse_id', $warehouseId);
    }

    /** Mutasi stok per produk: awal, masuk, keluar, akhir (satuan dasar) + nilai akhir pada HPP sekarang. */
    public function stockMutation(string $from, string $to, ?int $warehouseId = null, ?string $q = null): Collection
    {
        $base = fn () => $this->warehouseFilter(DB::table('stock_movements'), $warehouseId);

        $opening = $base()->whereDate('created_at', '<', $from)->groupBy('product_id')->selectRaw('product_id, SUM(qty_change) as q')->pluck('q', 'product_id');
        $moves = $base()->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->groupBy('product_id')
            ->selectRaw('product_id, SUM(CASE WHEN qty_change > 0 THEN qty_change ELSE 0 END) as in_q, SUM(CASE WHEN qty_change < 0 THEN -qty_change ELSE 0 END) as out_q')
            ->get()->keyBy('product_id');

        $ids = $opening->keys()->merge($moves->keys())->unique();

        return Product::whereIn('id', $ids)->when($q, fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")))
            ->orderBy('name')->get()->map(function (Product $p) use ($opening, $moves) {
                $open = Qty::toMilli($opening[$p->id] ?? 0);
                $in = Qty::toMilli($moves[$p->id]->in_q ?? 0);
                $out = Qty::toMilli($moves[$p->id]->out_q ?? 0);
                $close = $open + $in - $out;

                return ['product' => $p, 'opening' => $open, 'in' => $in, 'out' => $out, 'closing' => $close, 'value' => Qty::value(max(0, $close), (int) $p->cost)];
            });
    }

    /** Kartu stok satu produk: saldo awal + setiap mutasi dengan saldo berjalan. */
    public function stockCard(Product $product, string $from, string $to, ?int $warehouseId = null): array
    {
        $base = fn () => $this->warehouseFilter(DB::table('stock_movements')->where('stock_movements.product_id', $product->id), $warehouseId);

        $opening = Qty::toMilli($base()->whereDate('created_at', '<', $from)->sum('qty_change'));
        $rows = [];
        $balance = $opening;

        foreach ($base()->leftJoin('warehouses', 'warehouses.id', '=', 'stock_movements.warehouse_id')
            ->whereDate('stock_movements.created_at', '>=', $from)->whereDate('stock_movements.created_at', '<=', $to)
            ->orderBy('stock_movements.id')
            ->get(['stock_movements.*', 'warehouses.name as warehouse']) as $m) {
            $change = Qty::toMilli($m->qty_change);
            $balance += $change;
            $rows[] = ['m' => $m, 'in' => max($change, 0), 'out' => max(-$change, 0), 'balance' => $balance];
        }

        return ['opening' => $opening, 'rows' => $rows, 'closing' => $balance];
    }

    // ------------------------------------------------------------ Rekap pendapatan

    /** Uang yang diterima per metode, per kanal, dan per hari (tidak termasuk pesanan batal). */
    public function paymentRecap(string $from, string $to): array
    {
        $payments = DB::table('order_payments')->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->whereDate('order_payments.paid_at', '>=', $from)->whereDate('order_payments.paid_at', '<=', $to)
            ->get(['order_payments.kind', 'order_payments.method', 'order_payments.amount', 'order_payments.paid_at', 'orders.channel']);

        $sign = fn ($kind) => $kind === 'refund' ? -1 : 1;
        $byMethod = [];
        $byChannel = [];
        $byDay = [];

        foreach ($payments as $p) {
            $amount = (int) $p->amount * $sign($p->kind);
            $method = $p->kind === 'deposit' ? 'deposit' : $p->method;
            $group = $p->channel === 'web' ? 'online' : ($p->channel === 'admin' ? 'admin' : 'kasir');
            $day = substr((string) $p->paid_at, 0, 10);

            $byMethod[$method]['total'] = ($byMethod[$method]['total'] ?? 0) + $amount;
            $byMethod[$method]['count'] = ($byMethod[$method]['count'] ?? 0) + 1;
            $byChannel[$group]['total'] = ($byChannel[$group]['total'] ?? 0) + $amount;
            $byChannel[$group]['count'] = ($byChannel[$group]['count'] ?? 0) + 1;
            $byDay[$day][$method] = ($byDay[$day][$method] ?? 0) + $amount;
            $byDay[$day]['_total'] = ($byDay[$day]['_total'] ?? 0) + $amount;
        }

        ksort($byDay);
        $sales = Order::where('status', '!=', 'cancelled')->whereDate('ordered_at', '>=', $from)->whereDate('ordered_at', '<=', $to)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(grand_total),0) as total')->first();

        return [
            'by_method' => $byMethod, 'by_channel' => $byChannel, 'by_day' => $byDay,
            'received' => array_sum(array_column($byMethod, 'total')),
            'orders' => (int) $sales->n, 'billed' => (int) $sales->total,
        ];
    }

    // ----------------------------------------------------------- Kartu hutang/piutang

    /**
     * Buku pembantu dari jurnal: semua jurnal pada akun $accountKey yang sumbernya termasuk $sources.
     * Karena memakai jurnal, saldo kartu pasti cocok dengan buku besar.
     *
     * @param  array<string, array<int, int>>  $sources  [morphClass => [id, ...]]
     */
    public function subledger(string $accountKey, array $sources, string $from, string $to): array
    {
        $account = Account::where('key', $accountKey)->firstOrFail();

        $scope = function ($query) use ($account, $sources) {
            $query->where('journal_lines.account_id', $account->id)->where(function ($w) use ($sources) {
                $any = false;
                foreach ($sources as $type => $ids) {
                    if ($ids) {
                        $w->orWhere(fn ($x) => $x->where('journals.source_type', $type)->whereIn('journals.source_id', $ids));
                        $any = true;
                    }
                }
                $any || $w->whereRaw('1 = 0');
            });

            return $query;
        };

        $base = fn () => $scope(DB::table('journal_lines')->join('journals', 'journals.id', '=', 'journal_lines.journal_id'));

        $before = $base()->whereDate('journals.date', '<', $from)->selectRaw('COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as c')->first();
        $opening = $this->signed($account->normal_balance, (int) $before->d, (int) $before->c);

        $balance = $opening;
        $rows = [];
        foreach ($base()->whereDate('journals.date', '>=', $from)->whereDate('journals.date', '<=', $to)
            ->orderBy('journals.date')->orderBy('journals.id')->orderBy('journal_lines.id')
            ->get(['journals.id as journal_id', 'journals.number', 'journals.date', 'journals.description', 'journal_lines.debit', 'journal_lines.credit']) as $line) {
            $balance += $this->signed($account->normal_balance, (int) $line->debit, (int) $line->credit);
            $rows[] = ['line' => $line, 'balance' => $balance];
        }

        return ['opening' => $opening, 'rows' => $rows, 'closing' => $balance];
    }

    public function supplierPayableCard(Supplier $supplier, string $from, string $to): array
    {
        return $this->subledger('payable', [
            (new Purchase)->getMorphClass() => Purchase::where('supplier_id', $supplier->id)->pluck('id')->all(),
            (new PurchaseReturn)->getMorphClass() => PurchaseReturn::where('supplier_id', $supplier->id)->pluck('id')->all(),
            (new SupplierPayment)->getMorphClass() => SupplierPayment::where('supplier_id', $supplier->id)->pluck('id')->all(),
        ], $from, $to);
    }

    public function supplierReceivableCard(Supplier $supplier, string $from, string $to): array
    {
        return $this->subledger('supplier_receivable', [
            (new PurchaseReturn)->getMorphClass() => PurchaseReturn::where('supplier_id', $supplier->id)->pluck('id')->all(),
            (new SupplierPayment)->getMorphClass() => SupplierPayment::where('supplier_id', $supplier->id)->pluck('id')->all(),
        ], $from, $to);
    }

    public function customerReceivableCard(Customer $customer, string $from, string $to): array
    {
        $orders = Order::where('buyer_id', $customer->id)->pluck('id')->all();

        return $this->subledger('receivable', [
            (new Order)->getMorphClass() => $orders,
            (new SaleReturn)->getMorphClass() => SaleReturn::whereIn('order_id', $orders)->pluck('id')->all(),
            (new CustomerDeposit)->getMorphClass() => CustomerDeposit::where('customer_id', $customer->id)->pluck('id')->all(),
        ], $from, $to);
    }

    public function customerDepositCard(Customer $customer, string $from, string $to): array
    {
        return $this->subledger('customer_deposit', [
            (new CustomerDeposit)->getMorphClass() => CustomerDeposit::where('customer_id', $customer->id)->pluck('id')->all(),
            (new SaleReturn)->getMorphClass() => SaleReturn::whereIn('order_id', Order::where('buyer_id', $customer->id)->pluck('id'))->pluck('id')->all(),
        ], $from, $to);
    }
}
