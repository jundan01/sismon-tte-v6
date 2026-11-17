<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Urutan seeder:
     * 1. Master SKPD
     * 2. Master Jabatan & Golongan
     * 3. Superadmin User (admin@cirebonkota.go.id)
     */
    public function run(): void
    {
        $this->call([
            SkpdSeeder::class,
            MasterJabatanGolonganSeeder::class,
            SuperadminUserSeeder::class,
            SystemParameterSeeder::class,
        ]);
    }
}