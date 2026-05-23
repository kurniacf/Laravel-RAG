<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * State Spaced Repetition (algoritma SM-2) untuk satu kartu, milik satu user.
 * Perhitungan SM-2-nya ada di App\Services\Flashcard\SrsService.
 */
#[Fillable([
    'flashcard_id',
    'user_id',
    'ease_factor',
    'interval_days',
    'repetitions',
    'quality',
    'last_reviewed_at',
    'next_review_at',
])]
class FlashcardReview extends Model
{
    /** Ease factor awal SM-2 untuk kartu yang belum pernah direview. */
    public const DEFAULT_EASE_FACTOR = 2.5;

    /** Batas bawah ease factor sesuai SM-2. */
    public const MIN_EASE_FACTOR = 1.3;

    /** Jumlah repetisi sukses berturut-turut agar kartu dianggap "dikuasai". */
    public const MASTERED_REPETITIONS = 3;

    protected function casts(): array
    {
        return [
            'ease_factor' => 'float',
            'interval_days' => 'integer',
            'repetitions' => 'integer',
            'quality' => 'integer',
            'last_reviewed_at' => 'datetime',
            'next_review_at' => 'datetime',
        ];
    }

    public function flashcard(): BelongsTo
    {
        return $this->belongsTo(Flashcard::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Kartu dianggap dikuasai setelah beberapa kali diingat berturut-turut. */
    public function isMastered(): bool
    {
        return $this->repetitions >= self::MASTERED_REPETITIONS;
    }
}
