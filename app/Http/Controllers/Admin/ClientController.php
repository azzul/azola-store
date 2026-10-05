<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClientController extends Controller
{
    public function index()
    {
        return view('admin.clients.index', ['clients' => Client::orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['nullable', 'url', 'max:255'],
            'note' => ['nullable', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'logo' => ['nullable', 'image', 'max:1024'],
        ]);

        $client = new Client(collect($data)->except('logo')->all());
        $client->sort_order = $data['sort_order'] ?? 0;
        $client->is_published = true;
        if ($request->hasFile('logo')) {
            $client->logo_path = $request->file('logo')->store('clients', 'public');
        }
        $client->save();

        return back()->with('ok', 'Klien ditambahkan.');
    }

    public function toggle(Client $client)
    {
        $client->update(['is_published' => ! $client->is_published]);

        return back()->with('ok', $client->is_published ? 'Klien ditampilkan.' : 'Klien disembunyikan.');
    }

    public function destroy(Client $client)
    {
        if ($client->logo_path) {
            Storage::disk('public')->delete($client->logo_path);
        }
        $client->delete();

        return back()->with('ok', 'Klien dihapus.');
    }
}
