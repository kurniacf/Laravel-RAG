<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'subject_id',
    'title',
    'original_filename',
    'file_path',
    'file_size_bytes',
    'status',
    'page_count',
    'word_count',
    'total_chunks',
    'extracted_text',
    'error_message',
    'processed_at',
])]
class Document extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_READY,
        self::STATUS_FAILED,
    ];

    /** Batas halaman PDF yang boleh diproses lebih lanjut (chunking + embedding). */
    public const MAX_PAGES = 30;

    /** Batas ukuran file upload dalam byte (10 MB). */
    public const MAX_FILE_SIZE = 10 * 1024 * 1024;

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'file_size_bytes' => 'integer',
            'page_count' => 'integer',
            'word_count' => 'integer',
            'total_chunks' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu',
            self::STATUS_PROCESSING => 'Memproses',
            self::STATUS_READY => 'Siap',
            self::STATUS_FAILED => 'Gagal',
            default => $this->status,
        };
    }
}
