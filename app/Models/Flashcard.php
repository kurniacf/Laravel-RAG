<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'document_id',
    'user_id',
    'source_chunk_id',
    'front_text',
    'back_text',
    'difficulty',
    'position',
])]
class Flashcard extends Model
{
    public const DIFFICULTY_EASY = 'easy';

    public const DIFFICULTY_MEDIUM = 'medium';

    public const DIFFICULTY_HARD = 'hard';

    public const DIFFICULTIES = [
        self::DIFFICULTY_EASY,
        self::DIFFICULTY_MEDIUM,
        self::DIFFICULTY_HARD,
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceChunk(): BelongsTo
    {
        return $this->belongsTo(DocumentChunk::class, 'source_chunk_id');
    }

    /** State Spaced Repetition (SM-2) milik kartu ini, per user. */
    public function reviews(): HasMany
    {
        return $this->hasMany(FlashcardReview::class);
    }

    public function difficultyLabel(): string
    {
        return match ($this->difficulty) {
            self::DIFFICULTY_EASY => 'Mudah',
            self::DIFFICULTY_MEDIUM => 'Sedang',
            self::DIFFICULTY_HARD => 'Sulit',
            default => $this->difficulty,
        };
    }
}
