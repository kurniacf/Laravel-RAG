<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'icon', 'color_hex'])]
class Subject extends Model
{
    use HasFactory;

    /**
     * Daftar pilihan ikon standar (referensi internal — di-render lewat partials/subject-icon).
     */
    public const ICONS = [
        'book', 'beaker', 'code', 'calculator',
        'globe', 'chip', 'language', 'palette',
    ];

    /**
     * Pilihan warna preset (hex 7-char).
     */
    public const COLORS = [
        '#059669', // emerald
        '#2563eb', // blue
        '#d97706', // amber
        '#e11d48', // rose
        '#7c3aed', // violet
        '#475569', // slate
    ];

    /**
     * Auto-generate slug dari name saat saving (kecuali slug sudah diisi manual).
     */
    protected static function booted(): void
    {
        static::saving(function (Subject $subject) {
            if (empty($subject->slug) && ! empty($subject->name)) {
                $subject->slug = static::makeUniqueSlug($subject->name, $subject->id);
            }
        });
    }

    /**
     * Buat slug yang dijamin unik. Bila bentrok, tambahkan suffix -2, -3, dst.
     */
    public static function makeUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
