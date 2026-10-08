<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerDeposit;
use App\Models\FixedAsset;
use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Warehouse;
use App\Services\FinanceService;
use App\Services\OrderService;
use App\Services\PurchaseService;
use App\Services\ReconciliationService;
use App\Services\ReportService;
use App\Services\SalesDocumentService;
use App\Services\StockDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

/**
 * Uji acak: ratusan operasi bisnis (beli, bayar, jual, retur, alih gudang, opname, DP, batal, ...) dalam urutan acak.
 * Operasi yang ditolak sistem (stok kurang, melebihi retur, dst.) boleh, tetapi tidak boleh meninggalkan data setengah jadi.
 * Setelah itu semua buku harus cocok: jurnal seimbang, akun kontrol = buku pembantu, stok tidak minus.
 */
class ErpFuzzTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    private array $log = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounts();
    }

    /** @return array<int, int> */
    public static function seeds(): array
    {
        $n = (int) (getenv('FUZZ_SEEDS') ?: 8);

        return array_combine(range(1, $n), array_map(fn ($i) => [$i * 11], range(1, $n)));
    }

    #[DataProvider('seeds')]
    public function test_random_business_keeps_every_book_consistent(int $seed): void
    {
        mt_srand($seed);
        $p = fn (array $a) => $a[array_rand($a)];

        $suppliers = [Supplier::create(['name' => 'S1', 'term_days' => 7]), Supplier::create(['name' => 'S2', 'term_days' => 0])];
        $customers = [Customer::create(['name' => 'C1', 'phone' => '0811']), Customer::create(['name' => 'C2', 'phone' => '0822'])];
        $products = [];
        foreach ([[1500, 'pcs'], [8000, 'pcs'], [25000, 'kg'], [400, 'pcs']] as $i => [$price, $unit]) {
            $pr = $this->makeProduct(['price' => $price, 'unit' => $unit]);
            $pr->unitConversions()->create(['unit' => 'dus', 'factor' => 12]);
            $products[] = $pr;
        }
        $wh = [Warehouse::mainId(), Warehouse::create(['name' => 'B', 'code' => 'GB'])->id];

        $purchases = app(PurchaseService::class);
        $docs = app(StockDocumentService::class);
        $orders = app(OrderService::class);
        $sales = app(SalesDocumentService::class);
        $finance = app(FinanceService::class);
        $expenseId = Account::where('type', 'expense')->value('id');
        $cashClosed = false;

        $day = fn () => today()->subDays(mt_rand(0, 25))->toDateString();
        $items = function (int $max = 3) use ($products) {
            $keys = array_keys($products);
            shuffle($keys);
            $out = [];
            foreach (array_slice($keys, 0, mt_rand(1, min($max, count($keys)))) as $k) {
                $out[] = ['product_id' => $products[$k]->id, 'qty' => mt_rand(1, 20), 'price' => mt_rand(300, 20000)];
            }

            return $out;
        };
        $pick = fn ($model, $where = null) => self::rnd($where ? $model::query()->where($where[0], $where[1], $where[2] ?? null) : $model::query());

        for ($n = 1; $n <= (int) (getenv('FUZZ_OPS') ?: 220); $n++) {
            $op = mt_rand(1, 26);
            $this->log[] = "#$n op$op";
            $dbg = (int) getenv('FUZZ_DEBUG') === $n;
            $snap = fn () => Account::where('key', 'inventory')->first()->balance().' vs '.Product::all()->map(fn ($x) => $x->id.':'.$x->totalMilli().'@'.$x->cost.'='.\App\Support\Qty::value(max(0, $x->totalMilli()), (int) $x->cost))->implode(' ');
            if ($dbg) {
                fwrite(STDERR, "\nBEFORE ".$snap()."\n");
            }

            try {
                DB::transaction(function () use ($op, &$cashClosed, $suppliers, $customers, $products, $wh, $purchases, $docs, $orders, $sales, $finance, $expenseId, $p, $day, $items, $pick) {
                    switch (true) {
                        case $op <= 3: // pembelian
                            $m = $p(['cash', 'bank', 'credit']);
                            $purchases->create(['supplier_id' => $p($suppliers)->id, 'warehouse_id' => $p($wh), 'date' => $day(), 'payment_method' => $m,
                                'discount' => mt_rand(0, 1) ? mt_rand(0, 500) : 0, 'down_payment' => $m === 'credit' ? mt_rand(0, 2000) : 0, 'items' => $items()]);
                            break;
                        case $op === 4: // ubah faktur
                            if ($x = $pick(Purchase::class, ['status', '=', 'posted'])) {
                                $purchases->update($x, ['supplier_id' => $x->supplier_id, 'warehouse_id' => $x->warehouse_id, 'date' => $x->date->toDateString(), 'payment_method' => $x->payment_method, 'items' => $items()]);
                            }
                            break;
                        case $op === 5: // batal faktur
                            if ($x = $pick(Purchase::class, ['status', '=', 'posted'])) {
                                $purchases->cancel($x);
                            }
                            break;
                        case $op === 6 || $op === 7: // bayar hutang
                            $s = $p($suppliers);
                            if (($d = $s->payable()) > 0) {
                                $purchases->pay($s, mt_rand(1, $d), $p(['cash', 'bank']), $day());
                            }
                            break;
                        case $op === 8: // batal bayar
                            if ($x = $pick(SupplierPayment::class, ['status', '=', 'posted'])) {
                                $purchases->cancelPayment($x);
                            }
                            break;
                        case $op === 9: // retur beli
                            if ($x = $pick(Purchase::class, ['status', '=', 'posted'])) {
                                $row = self::rnd($x->items());
                                $purchases->createReturn(['purchase_id' => $x->id, 'supplier_id' => $x->supplier_id, 'warehouse_id' => $x->warehouse_id,
                                    'settlement' => $p(['payable', 'cash', 'bank', 'receivable']), 'items' => [['product_id' => $row->product_id, 'qty' => mt_rand(1, 6), 'price' => $row->price]]]);
                            }
                            break;
                        case $op === 10:
                            if ($x = $pick(PurchaseReturn::class, ['status', '=', 'posted'])) {
                                $purchases->cancelReturn($x);
                            }
                            break;
                        case $op === 11: // terima dana supplier
                            $s = $p($suppliers);
                            if (($d = $s->receivable()) > 0) {
                                $purchases->receive($s, mt_rand(1, $d), $p(['cash', 'bank']));
                            }
                            break;
                        case $op <= 15: // penjualan admin / kasir
                            $rows = array_map(fn ($r) => ['product_id' => $r['product_id'], 'qty' => mt_rand(1, 5), 'discount' => mt_rand(0, 3) === 0 ? mt_rand(0, 200) : 0], $items(2));
                            $orders->create(['items' => $rows, 'payment_method' => $p(['cash', 'transfer', 'qris', 'debit']), 'buyer_id' => $p($customers)->id,
                                'paid_total' => mt_rand(0, 2) ? null : mt_rand(0, 3000), 'order_discount' => mt_rand(0, 4) === 0 ? mt_rand(0, 300) : 0], $p(['admin', 'pos_desktop', 'pos_android']));
                            break;
                        case $op === 16: // bayar pesanan
                            if (($x = self::rnd(Order::where('status', '!=', 'cancelled'))) && $x->outstanding() > 0) {
                                $orders->recordPayment($x, mt_rand(1, $x->outstanding()), $p(['cash', 'transfer']));
                            }
                            break;
                        case $op === 17 || $op === 18: // retur jual
                            if ($x = $pick(Order::class, ['status', '!=', 'cancelled'])) {
                                $it = self::rnd($x->items());
                                if ($it) {
                                    $sales->createReturn($x, ['items' => [['order_item_id' => $it->id, 'qty' => mt_rand(1, 3), 'restock' => (bool) mt_rand(0, 1)]], 'refund_method' => $p(['cash', 'transfer', 'deposit'])]);
                                }
                            }
                            break;
                        case $op === 19: // batal pesanan
                            if ($x = $pick(Order::class, ['status', '!=', 'cancelled'])) {
                                $orders->cancel($x);
                            }
                            break;
                        case $op === 20: // alih gudang kirim
                            $from = $p($wh);
                            $docs->createTransfer(['from_warehouse_id' => $from, 'to_warehouse_id' => $from === $wh[0] ? $wh[1] : $wh[0], 'date' => $day(),
                                'items' => array_map(fn ($r) => ['product_id' => $r['product_id'], 'qty' => mt_rand(1, 8)], $items(2))]);
                            break;
                        case $op === 21: // terima / batal alih gudang
                            if ($x = $pick(StockTransfer::class, ['status', '=', 'sent'])) {
                                if (mt_rand(0, 2)) {
                                    $recv = [];
                                    foreach ($x->items as $i) {
                                        $recv[$i->product_id] = mt_rand(0, 1) ? (float) $i->qty : max(0, (float) $i->qty - mt_rand(0, 2));
                                    }
                                    $docs->receiveTransfer($x, $recv);
                                } else {
                                    $docs->cancelTransfer($x);
                                }
                            }
                            break;
                        case $op === 22: // penyesuaian
                            $docs->createAdjustment(['reason' => $p(['damaged', 'lost', 'found', 'correction']), 'warehouse_id' => $p($wh),
                                'items' => [['product_id' => $p($products)->id, 'qty_change' => mt_rand(-5, 5)]]]);
                            break;
                        case $op === 23: // opname
                            $o = $docs->startOpname(['warehouse_id' => $p($wh)]);
                            $counts = [];
                            foreach ($o->items as $i) {
                                $counts[$i->id] = mt_rand(0, 1) ? '' : (string) mt_rand(0, 25);
                            }
                            $docs->saveCounts($o->load('items'), $counts);
                            $docs->finalizeOpname($o);
                            break;
                        case $op === 24: // DP / pakai / kembalikan / kelebihan bayar
                            $dp = $sales->createDeposit(['customer_id' => $p($customers)->id, 'amount' => mt_rand(1000, 9000), 'method' => $p(['cash', 'transfer'])]);
                            if (($x = self::rnd(Order::where('status', '!=', 'cancelled')->where('buyer_id', $dp->customer_id))) && $x->outstanding() > 0 && mt_rand(0, 1)) {
                                $sales->applyDeposit($dp, $x, min($dp->balance(), $x->outstanding()));
                            } elseif (mt_rand(0, 1)) {
                                $sales->refundDeposit($dp, mt_rand(1, $dp->balance()), $p(['cash', 'transfer']));
                            }
                            if ($x = $pick(Order::class, ['status', '!=', 'cancelled'])) {
                                $sales->recordOverpayment($x, mt_rand(100, 2000), (bool) mt_rand(0, 1));
                            }
                            break;
                        case $op === 25: // biaya, jurnal manual, aset, depresiasi, kas
                            $finance->cashEntry(['kind' => $p(['expense', 'income']), 'account_id' => $expenseId, 'via' => $p(['cash', 'bank']), 'amount' => mt_rand(100, 5000), 'date' => $day()]);
                            if (mt_rand(0, 3) === 0) {
                                $finance->addAsset(['name' => 'Aset', 'cost' => mt_rand(100, 900) * 1000, 'salvage' => 0, 'life_months' => mt_rand(3, 24), 'acquired_on' => today()->subMonths(mt_rand(0, 5))->toDateString(), 'paid_via' => $p(['cash', 'bank', 'payable', 'equity'])]);
                            }
                            $finance->depreciate(now()->format('Y-m'));
                            if (! $cashClosed && mt_rand(0, 5) === 0) {
                                $finance->closeCash(['date' => today()->toDateString(), 'denominations' => ['1000' => mt_rand(0, 50)]]);
                                $cashClosed = true;
                            }
                            break;
                        default: // pesanan online lengkap
                            [$w] = $orders->create(['items' => [['product_id' => $p($products)->id, 'qty' => mt_rand(1, 3)]], 'payment_method' => $p(['transfer', 'cod']), 'customer_name' => 'Web', 'customer_phone' => '0899'.mt_rand(10, 99)], 'web');
                            if ($w->payment_method === 'transfer' && mt_rand(0, 1)) {
                                $orders->recordPayment($w, $w->grand_total, 'transfer');
                            }
                            if (mt_rand(0, 2) === 0) {
                                $orders->ship($w->refresh(), 'JNE', 'R'.$w->id);
                            }
                            if (mt_rand(0, 3) === 0) {
                                $orders->cancel($w->refresh());
                            }
                    }
                });
            } catch (ValidationException|InsufficientStockException) {
                // Ditolak dengan sah. Transaksi dibatalkan; invarian di bawah membuktikan tidak ada sisa.
                $this->assertInvariants("setelah penolakan {$this->log[array_key_last($this->log)]}");
            }

            if ($dbg) {
                fwrite(STDERR, 'AFTER '.$snap()."\n");
                foreach (Purchase::with('items')->get() as $pu) {
                    fwrite(STDERR, "PUR#{$pu->id} {$pu->status} wh{$pu->warehouse_id} gt{$pu->grand_total} ".$pu->items->map(fn ($i) => "p{$i->product_id}x{$i->base_qty}@{$i->price}={$i->net_total}")->implode(',')."\n");
                }
                foreach (DB::table('journals')->orderBy('id')->get() as $j) {
                    $ls = DB::table('journal_lines as l')->join('accounts as a', 'a.id', '=', 'l.account_id')->where('journal_id', $j->id)->get()->map(fn ($l) => $l->key.' D'.$l->debit.' C'.$l->credit)->implode(', ');
                    fwrite(STDERR, "JRN#{$j->id} {$j->type} {$j->source_type}#{$j->source_id} $ls\n");
                }
                foreach (DB::table('stock_movements')->orderBy('id')->get() as $m) {
                    fwrite(STDERR, 'MOV '.json_encode($m)."\n");
                }
            }

            if ($n % (int) (getenv('FUZZ_EVERY') ?: 20) === 0) {
                $this->assertInvariants("operasi ke-$n");
            }
        }

        $this->assertInvariants('akhir');
        $this->assertGreaterThan(40, Account::where('key', 'inventory')->first()->lines()->count(), 'Skenario acak harus benar-benar menjalankan transaksi.');
    }

    /** Pilih baris acak secara deterministik (mt_rand), supaya satu seed selalu menghasilkan skenario yang sama. */
    private static function rnd($query)
    {
        $rows = $query->orderBy('id')->get();

        return $rows->isEmpty() ? null : $rows[mt_rand(0, $rows->count() - 1)];
    }

    private function assertInvariants(string $when): void
    {
        $reports = app(ReportService::class);
        $failed = collect(app(ReconciliationService::class)->run()['checks'])->where('ok', false)->map(fn ($c) => $c['title'].' '.json_encode($c['rows']))->all();
        $this->assertSame([], $failed, "Rekonsiliasi gagal $when | ".implode(' ', array_slice($this->log, -6)));

        $trial = $reports->trial('2000-01-01', '2100-01-01');
        $this->assertSame($trial['debit'], $trial['credit'], "Neraca saldo tidak seimbang $when");
        $this->assertTrue($reports->balanceSheet('2100-01-01')['totals']['balanced'], "Neraca tidak seimbang $when | ".json_encode($reports->balanceSheet('2100-01-01')['totals']).' | '.implode(' ', array_slice($this->log, -4)).' | '.Account::all()->map(fn ($a) => $a->key.':'.$a->type.':'.$a->netDebit())->filter(fn ($x) => ! str_ends_with($x, ':0'))->implode(' '));

        $bal = fn (string $key) => Account::where('key', $key)->first()->balance();

        $payable = Supplier::all()->sum(fn ($s) => $s->payable()) + (int) Purchase::whereNull('supplier_id')->where('status', 'posted')->selectRaw('COALESCE(SUM(grand_total - paid_total),0) v')->value('v')
            // Aset yang dibeli secara kredit juga masuk Utang Usaha (tanpa pemasok).
            + (int) DB::table('journal_lines as l')->join('journals as j', 'j.id', '=', 'l.journal_id')->where('l.account_id', Account::where('key', 'payable')->value('id'))
                ->where('j.source_type', FixedAsset::class)->selectRaw('COALESCE(SUM(l.credit - l.debit),0) v')->value('v');
        $diag = '';
        if ($payable !== $bal('payable')) {
            $acc = Account::where('key', 'payable')->first();
            $rows = DB::table('journal_lines as l')->join('journals as j', 'j.id', '=', 'l.journal_id')->where('l.account_id', $acc->id)
                ->selectRaw('j.source_type, j.source_id, SUM(l.credit - l.debit) v')->groupBy('j.source_type', 'j.source_id')->get();
            $bad = [];
            foreach ($rows as $r) {
                $expect = $r->source_type === Purchase::class ? 0 : null;
                if ($r->source_type === Purchase::class) {
                    $pu = Purchase::find($r->source_id);
                    $expect = $pu->status === 'posted' ? $pu->grand_total - $pu->paid_total : 0;
                    if ((int) $r->v !== $expect) {
                        $bad[] = "Purchase#{$r->source_id}({$pu->status}) ledger={$r->v} expect=$expect gt={$pu->grand_total} paid={$pu->paid_total}";
                    }
                } elseif ((int) $r->v !== 0) {
                    $bad[] = "{$r->source_type}#{$r->source_id} ledger={$r->v}";
                }
            }
            $diag = ' | '.implode('; ', $bad).' | '.implode(' ', array_slice($this->log, -8));
        }
        $this->assertSame($payable, $bal('payable'), "Akun Utang Usaha != jumlah hutang per faktur $when$diag");
        $rdiag = '';
        if (Supplier::all()->sum(fn ($s) => $s->receivable()) !== $bal('supplier_receivable')) {
            $acc = Account::where('key', 'supplier_receivable')->first();
            $rdiag = ' | '.DB::table('journal_lines as l')->join('journals as j', 'j.id', '=', 'l.journal_id')->where('l.account_id', $acc->id)
                ->selectRaw('j.source_type, j.source_id, j.type, j.description, l.debit, l.credit')->get()->map(fn ($r) => class_basename($r->source_type)."#{$r->source_id} {$r->description} D{$r->debit} C{$r->credit}")->implode('; ')
                .' | '.PurchaseReturn::all()->map(fn ($r) => "R#{$r->id} {$r->status} {$r->settlement} {$r->total} s{$r->supplier_id}")->implode('; ')
                .' | '.SupplierPayment::all()->map(fn ($r) => "P#{$r->id} {$r->status} {$r->direction} {$r->amount} s{$r->supplier_id}")->implode('; ').' | '.implode(' ', array_slice($this->log, -8));
        }
        $this->assertSame(Supplier::all()->sum(fn ($s) => $s->receivable()), $bal('supplier_receivable'), "Piutang supplier != kartu $when$rdiag");
        $this->assertSame((int) CustomerDeposit::all()->sum(fn ($d) => $d->balance()), $bal('customer_deposit'), "Akun DP != saldo DP $when");
        $this->assertSame((int) FixedAsset::sum('depreciated'), $bal('accumulated_depreciation'), "Akumulasi penyusutan != aset $when");
        $this->assertSame(0, (int) DB::table('products')->where('stock_qty', '<', 0)->count(), "Stok toko minus $when");
        $this->assertSame(0, (int) DB::table('stock_balances')->where('qty', '<', 0)->count(), "Stok gudang minus $when");
    }
}
