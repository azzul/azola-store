<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index()
    {
        return view('admin.devices.index', [
            'tokens' => ApiToken::with('user')->latest('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'device_type' => ['required', 'in:desktop,android'],
        ]);

        [, $plain] = ApiToken::issue($request->user(), $data['name'], $data['device_type']);

        // Teks token hanya tampil sekali (lewat flash), database hanya menyimpan hash-nya.
        return redirect()->route('admin.devices.index')->with('new_token', $plain);
    }

    public function revoke(ApiToken $token)
    {
        $token->forceFill(['revoked_at' => now()])->save();

        return back()->with('ok', 'Token dicabut. Perangkat itu harus login ulang.');
    }
}
