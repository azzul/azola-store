<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Etalase;
use Illuminate\Http\Request;

class EtalaseController extends Controller
{
    public function index()
    {
        return view('admin.etalases.index', ['etalases' => Etalase::withCount('groups')->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Etalase::create($data + ['slug' => Etalase::uniqueSlug($data['name'])]);

        return back()->with('ok', 'Etalase ditambahkan. Pilih produknya lewat halaman Katalog.');
    }

    public function update(Request $request, Etalase $etalase)
    {
        $etalase->update($this->validated($request));

        return back()->with('ok', 'Etalase disimpan.');
    }

    public function destroy(Etalase $etalase)
    {
        $etalase->delete();

        return back()->with('ok', 'Etalase dihapus. Produknya tetap ada di katalog.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return $data + ['sort_order' => $data['sort_order'] ?? 0, 'is_visible' => $request->boolean('is_visible', true)];
    }
}
