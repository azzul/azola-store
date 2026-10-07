<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\StoreSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function index()
    {
        $stored = StoreSettings::stored();
        $fields = collect(StoreSettings::FIELDS)->map(function ($f, $key) use ($stored) {
            $current = $stored[$key] ?? null;
            $default = config($f[0]);
            if ($f[3] === 'hours') {
                $default = StoreSettings::hoursToText((array) $default);
            }

            return ['key' => $key, 'label' => $f[1], 'group' => $f[2], 'type' => $f[3], 'value' => $current ?? (is_scalar($default) || $default === null ? (string) $default : '')];
        })->groupBy('group');

        return view('admin.settings.index', ['groups' => $fields, 'logo' => $stored['logo'] ?? null]);
    }

    public function update(Request $request)
    {
        $rules = ['logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024']];
        foreach (StoreSettings::FIELDS as $key => [, , , $type]) {
            $rules[$key] = match ($type) {
                'email' => ['nullable', 'email', 'max:120'],
                'url' => ['nullable', 'url:http,https', 'max:300'],
                'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'number' => ['nullable', 'integer', 'min:0'],
                'year' => ['nullable', 'digits:4'],
                'phone' => ['nullable', 'regex:/^[0-9+\s\-]{6,20}$/'],
                'username' => ['nullable', 'string', 'max:60', 'regex:/^@?[A-Za-z0-9._]+$/'],
                'textarea', 'hours' => ['nullable', 'string', 'max:600'],
                default => ['nullable', 'string', 'max:160'],
            };
        }
        $data = $request->validate($rules, [
            'color.regex' => 'Warna harus berformat #RRGGBB.',
            '*.regex' => 'Format isian tidak sesuai.',
            'logo.mimes' => 'Logo harus PNG, JPG, WebP, atau SVG.',
            'logo.max' => 'Ukuran logo maksimal 1 MB.',
        ]);

        foreach (StoreSettings::FIELDS as $key => [, , , $type]) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($type === 'phone') {
                $value = preg_replace('/\D/', '', $value);
            } elseif ($type === 'username') {
                $value = ltrim($value, '@');
            }

            $this->put($key, $value);
        }

        $old = Setting::where('key', 'logo')->value('value');
        if ($request->boolean('remove_logo')) {
            $this->dropLogo($old);
            $this->put('logo', '');
        }
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $path = $file->storeAs('branding', 'logo-'.Str::lower(Str::random(8)).'.'.$file->getClientOriginalExtension(), 'public');
            $this->dropLogo($old);
            $this->put('logo', 'storage/'.$path);
        }

        return back()->with('ok', 'Pengaturan disimpan.');
    }

    /** Kosong = hapus baris supaya kembali ke nilai bawaan (.env / config). */
    private function put(string $key, string $value): void
    {
        if ($value === '') {
            Setting::where('key', $key)->get()->each->delete();

            return;
        }

        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    private function dropLogo(?string $path): void
    {
        if ($path && str_starts_with($path, 'storage/')) {
            Storage::disk('public')->delete(substr($path, 8));
        }
    }
}
