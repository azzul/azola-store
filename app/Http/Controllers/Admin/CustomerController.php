<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PriceLevel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $customers = Customer::with('priceLevel')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->when($request->query('status') === 'off', fn ($w) => $w->where('is_active', false))
            ->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.customers.index', ['customers' => $customers, 'q' => $q]);
    }

    public function create()
    {
        return view('admin.customers.form', ['customer' => new Customer(['is_active' => true]), 'levels' => PriceLevel::orderBy('sort_order')->get()]);
    }

    public function store(Request $request)
    {
        $customer = Customer::create($this->validated($request));

        return redirect()->route('admin.customers.show', $customer)->with('ok', 'Customer ditambahkan.');
    }

    public function show(Customer $customer)
    {
        return view('admin.customers.show', [
            'customer' => $customer->load('priceLevel'),
            'orders' => $customer->orders()->latest('ordered_at')->limit(15)->get(),
            'deposits' => $customer->deposits()->latest('date')->latest('id')->limit(10)->get(),
        ]);
    }

    public function edit(Customer $customer)
    {
        return view('admin.customers.form', ['customer' => $customer, 'levels' => PriceLevel::orderBy('sort_order')->get()]);
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.customers.show', $customer)->with('ok', 'Customer diperbarui.');
    }

    public function destroy(Customer $customer)
    {
        if ($customer->orders()->exists() || $customer->deposits()->exists()) {
            return back()->withErrors(['customer' => 'Customer sudah punya transaksi. Nonaktifkan saja agar riwayatnya tetap utuh.']);
        }
        $customer->delete();

        return redirect()->route('admin.customers.index')->with('ok', 'Customer dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'price_level_id' => ['nullable', Rule::exists('price_levels', 'id')],
            'credit_limit' => ['nullable', 'integer', 'min:0'], 'note' => ['nullable', 'string', 'max:500'],
        ]);

        $data['phone'] = $data['phone'] ? preg_replace('/\D/', '', $data['phone']) : null;
        $data['credit_limit'] = (int) ($data['credit_limit'] ?? 0);

        if ($data['phone'] && Customer::where('phone', $data['phone'])->where('id', '!=', $request->route('customer')?->id ?? 0)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['phone' => 'Nomor telepon ini sudah dipakai customer lain.']);
        }

        return $data;
    }
}
