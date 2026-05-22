<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        // Empat mata pelajaran untuk demo (sengaja sedikit, agar fokus).
        $subjects = [
            ['Matematika',        'matematika',        'calculator', '#2563eb'],
            ['Fisika',            'fisika',            'beaker',     '#7c3aed'],
            ['Biologi',           'biologi',           'book',       '#059669'],
            ['Pemrograman Dasar', 'pemrograman-dasar', 'code',       '#475569'],
        ];

        foreach ($subjects as [$name, $slug, $icon, $color]) {
            Subject::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'icon' => $icon,
                    'color_hex' => $color,
                    'documents_count' => 0,
                ],
            );
        }
    }
}
