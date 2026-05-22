<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'document_id',
    'user_id',
    'title',
    'difficulty',
    'question_count',
    'total_attempts',
    'average_score',
])]
class Quiz extends Model
{
    // Nama tabel eksplisit — hindari ambiguitas pluralisasi "Quiz".
    protected $table = 'quizzes';

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
            'question_count' => 'integer',
            'total_attempts' => 'integer',
            'average_score' => 'integer',
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

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function difficultyLabel(): string
    {
        return self::difficultyLabelFor($this->difficulty);
    }

    public static function difficultyLabelFor(string $difficulty): string
    {
        return match ($difficulty) {
            self::DIFFICULTY_EASY => 'Mudah',
            self::DIFFICULTY_MEDIUM => 'Sedang',
            self::DIFFICULTY_HARD => 'Sulit',
            default => $difficulty,
        };
    }
}
