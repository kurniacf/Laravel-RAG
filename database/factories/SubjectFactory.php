<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Matematika Dasar',
            'Bahasa Indonesia',
            'Sejarah Nusantara',
            'Fisika Modern',
            'Pemrograman Web',
            'Biologi Sel',
            'Kimia Organik',
            'Ekonomi Mikro',
            'Geografi Regional',
            'Sosiologi',
        ]).' '.fake()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'icon' => fake()->randomElement(Subject::ICONS),
            'color_hex' => fake()->randomElement(Subject::COLORS),
            'documents_count' => 0,
        ];
    }
}
