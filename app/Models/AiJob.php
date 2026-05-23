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

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_RUNNING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    public const TYPES = [
        self::TYPE_PARSE,
        self::TYPE_CHUNK,
        self::TYPE_EMBED,
        self::TYPE_SUMMARIZE,
        self::TYPE_QUIZ_GEN,
        self::TYPE_FLASHCARD_GEN,
    ];

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

    public function typeLabel(): string
    {
        return self::typeLabelFor($this->job_type);
    }

    public static function typeLabelFor(string $type): string
    {
        return match ($type) {
            self::TYPE_PARSE => 'Ekstraksi PDF',
            self::TYPE_CHUNK => 'Pemecahan Chunk',
            self::TYPE_EMBED => 'Pembuatan Embedding',
            self::TYPE_SUMMARIZE => 'Pembuatan Ringkasan',
            self::TYPE_QUIZ_GEN => 'Pembuatan Kuis',
            self::TYPE_FLASHCARD_GEN => 'Pembuatan Flashcard',
            default => $type,
        };
    }

    public function statusLabel(): string
    {
        return self::statusLabelFor($this->status);
    }

    public static function statusLabelFor(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'Menunggu',
            self::STATUS_RUNNING => 'Berjalan',
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_FAILED => 'Gagal',
            default => $status,
        };
    }

    /**
     * Durasi job sebagai string ramah baca (mis. "1.2 s", "850 ms").
     * Mengembalikan null bila belum diketahui.
     */
    public function durationLabel(): ?string
    {
        if ($this->duration_ms === null) {
            return null;
        }

        return $this->duration_ms >= 1000
            ? number_format($this->duration_ms / 1000, 2).' s'
            : $this->duration_ms.' ms';
    }
}
