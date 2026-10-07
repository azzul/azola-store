<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Data toko contoh (alamat, kontak, sosmed, jam buka) yang disimpan di database.
 * Tidak menimpa isian yang sudah diubah admin. Ganti lewat admin > Pengaturan toko sebelum dipakai klien.
 */
class DemoSettingSeeder extends Seeder
{
    public function run(): void
    {
        $dummy = [
            'tagline' => 'Belanja online, stok selalu sama dengan di toko.',
            'since' => '2016',
            'whatsapp' => '6281234567890',
            'email' => 'halo@azolastore.test',
            'address' => 'Jl. Pandanaran No. 88, Mugassari, Semarang Selatan, Kota Semarang 50249',
            'map_url' => 'https://www.google.com/maps/search/?api=1&query=Jl.+Pandanaran+88+Semarang',
            'hours' => "Senin - Sabtu | 08.00 - 17.00\nMinggu | Tutup",
            'instagram' => 'azolastore',
            'tiktok' => 'azolastore',
            'facebook' => 'https://facebook.com/azolastore',
            'youtube' => 'https://youtube.com/@azolastore',
            'bank_name' => 'Bank Contoh',
            'bank_account' => '1234567890',
            'bank_holder' => 'Azola Store',
        ];

        foreach ($dummy as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
