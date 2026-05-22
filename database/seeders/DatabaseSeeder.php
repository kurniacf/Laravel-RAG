<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed urutan: User → Subject → Document.
     * Dokumen butuh user dan subject sudah ada, jadi tidak boleh dibalik.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SubjectSeeder::class,
            DocumentSeeder::class,
        ]);

        $this->printAdminCredentials();
    }

    /**
     * Cetak kredensial admin secara prominent agar mudah dibaca saat
     * `php artisan db:seed` atau `php artisan migrate:fresh --seed`.
     */
    protected function printAdminCredentials(): void
    {
        $cmd = $this->command;
        if (! $cmd) {
            return;
        }

        $line = str_repeat('─', 60);

        $cmd->newLine();
        $cmd->line('<fg=green>'.$line.'</>');
        $cmd->line('<fg=green;options=bold>  Kredensial Akun Demo (semua password sama)</>');
        $cmd->line('<fg=green>'.$line.'</>');
        $cmd->line('  Admin  → <options=bold>admin@pintarbelajar.test</>');
        $cmd->line('  User   → <options=bold>andi@pintarbelajar.test</>, rina@..., budi@...');
        $cmd->line('  Password → <fg=yellow;options=bold>'.UserSeeder::DEFAULT_PASSWORD.'</>');
        $cmd->line('<fg=green>'.$line.'</>');
        $cmd->newLine();
    }
}
