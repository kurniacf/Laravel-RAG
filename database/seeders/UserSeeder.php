<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Password default untuk SEMUA akun demo (admin + user).
     */
    public const DEFAULT_PASSWORD = 'PintarBelajar2026!';

    public function run(): void
    {
        // Admin default.
        User::firstOrCreate(
            ['email' => 'admin@pintarbelajar.test'],
            [
                'name' => 'Administrator',
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ],
        );

        // Tiga user biasa dengan nama Indonesia realistis (cukup untuk demo).
        $users = [
            ['Andi Pratama', 'andi@pintarbelajar.test'],
            ['Rina Lestari', 'rina@pintarbelajar.test'],
            ['Budi Santoso', 'budi@pintarbelajar.test'],
        ];

        foreach ($users as [$name, $email]) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(self::DEFAULT_PASSWORD),
                    'role' => User::ROLE_USER,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
