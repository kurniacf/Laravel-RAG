<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'quiz_id',
    'source_chunk_id',
    'type',
    'question_text',
    'correct_answer',
    'explanation',
    'difficulty',
    'position',
])]
class QuizQuestion extends Model
{
    /** Pilihan ganda — empat opsi, satu benar. */
    public const TYPE_MCQ = 'mcq';

    /** Benar / Salah — dua opsi. */
    public const TYPE_TRUE_FALSE = 'true_false';

    /** Jawaban singkat — isian teks bebas. */
    public const TYPE_SHORT_ANSWER = 'short_answer';

    public const TYPES = [
        self::TYPE_MCQ,
        self::TYPE_TRUE_FALSE,
        self::TYPE_SHORT_ANSWER,
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizOption::class)->orderBy('position');
    }

    public function sourceChunk(): BelongsTo
    {
        return $this->belongsTo(DocumentChunk::class, 'source_chunk_id');
    }

    /** Apakah soal ini berbasis pilihan (mcq / true_false)? */
    public function isOptionBased(): bool
    {
        return in_array($this->type, [self::TYPE_MCQ, self::TYPE_TRUE_FALSE], true);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_MCQ => 'Pilihan Ganda',
            self::TYPE_TRUE_FALSE => 'Benar / Salah',
            self::TYPE_SHORT_ANSWER => 'Jawaban Singkat',
            default => $this->type,
        };
    }
}
