<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id',
    'job_type',
    'status',
    'started_at',
    'finished_at',
    'duration_ms',
    'tokens_used',
    'error_message',
])]
class AiJob extends Model
{
    public const TYPE_PARSE = 'parse';

    public const TYPE_CHUNK = 'chunk';

    public const TYPE_EMBED = 'embed';

    /** Tier 2 — pembuatan ringkasan otomatis tiga tingkat. */
    public const TYPE_SUMMARIZE = 'summarize';

    /** Tier 2 — pembuatan soal kuis dari dokumen. */
    public const TYPE_QUIZ_GEN = 'quiz_gen';

    /** Tier 3 — pembuatan flashcard dari dokumen. */
    public const TYPE_FLASHCARD_GEN = 'flashcard_gen';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'tokens_used' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
