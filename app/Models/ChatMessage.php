<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['chat_session_id', 'role', 'content', 'source', 'cited_chunk_ids', 'tokens_used'])]
class ChatMessage extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    /** Jawaban berbasis konteks dokumen yang diunggah user. */
    public const SOURCE_DOCUMENT = 'document';

    /** Jawaban berbasis pengetahuan umum (di luar dokumen, masih relate). */
    public const SOURCE_GENERAL = 'general';

    /** AI menolak menjawab (di luar topik, manipulasi, atau tidak ada konteks). */
    public const SOURCE_REFUSED = 'refused';

    protected function casts(): array
    {
        return [
            'cited_chunk_ids' => 'array',
            'tokens_used' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }
}
