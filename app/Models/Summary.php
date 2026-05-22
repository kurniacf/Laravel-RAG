<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id',
    'type',
    'content',
    'word_count',
    'tokens_used',
    'model_used',
])]
class Summary extends Model
{
    /** Ringkasan eksekutif: satu paragraf gambaran menyeluruh. */
    public const TYPE_EXECUTIVE = 'executive';

    /** Ringkasan per bagian/bab: terstruktur dengan subjudul. */
    public const TYPE_PER_CHAPTER = 'per_chapter';

    /** Poin kunci: daftar bullet hal terpenting. */
    public const TYPE_KEY_POINTS = 'key_points';

    /** Urutan tampil sekaligus daftar tipe yang valid. */
    public const TYPES = [
        self::TYPE_EXECUTIVE,
        self::TYPE_PER_CHAPTER,
        self::TYPE_KEY_POINTS,
    ];

    protected function casts(): array
    {
        return [
            'word_count' => 'integer',
            'tokens_used' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** Label tipe untuk ditampilkan di UI. */
    public function typeLabel(): string
    {
        return self::labelFor($this->type);
    }

    public static function labelFor(string $type): string
    {
        return match ($type) {
            self::TYPE_EXECUTIVE => 'Ringkasan Eksekutif',
            self::TYPE_PER_CHAPTER => 'Ringkasan Per Bagian',
            self::TYPE_KEY_POINTS => 'Poin Kunci',
            default => $type,
        };
    }
}
