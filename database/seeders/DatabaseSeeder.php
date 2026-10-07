<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Akun admin awal diambil dari .env (SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD).
     * Ganti password-nya sebelum dipakai di server.
     */
    public function run(): void
    {
        $this->call(AccountSeeder::class);

        $admin = User::firstOrNew(['email' => env('SEED_ADMIN_EMAIL', 'admin@azola.test')]);
        if (! $admin->exists) {
            $admin->name = 'Admin';
            $admin->password = env('SEED_ADMIN_PASSWORD') ?: \Illuminate\Support\Str::random(20);
        }
        $admin->role = User::ROLE_ADMIN;
        $admin->save();

        $this->call(DemoStoreSeeder::class);
        $this->call(DemoErpSeeder::class);
        $this->call(DemoArticleSeeder::class);
        $this->call(DemoSettingSeeder::class);
    }
}
