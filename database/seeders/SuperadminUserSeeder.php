<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder akun default Superadmin — TOTP MFA diaktifkan saat pertama login.
 * Ref: SECURITY.md §6.2
 */
class SuperadminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@cirebonkota.go.id'],
            [
                'name'           => 'Superadmin Persandian Kota Cirebon',
                'password'       => 'SuperAdmin2026!@#',
                'is_mfa_enabled' => false,
                'role'           => 'superadmin',
            ]
        );
    }
}
