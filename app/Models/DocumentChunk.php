<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id',
    'chunk_index',
    'content',
    'page_number',
    'token_count',
])]
class DocumentChunk extends Model
{
    /**
     * Kolom `embedding` sengaja TIDAK di-fillable karena tipenya bergantung
     * driver DB (vector di pgsql, TEXT JSON di sqlite). Diisi lewat raw SQL
     * di EmbeddingService agar format-nya tepat per driver.
     */
    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'page_number' => 'integer',
            'token_count' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
