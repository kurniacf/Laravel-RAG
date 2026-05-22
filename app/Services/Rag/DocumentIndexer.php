<?php

namespace App\Services\Rag;

use App\Models\AiJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Orchestrator: pecah teks dokumen jadi chunk → simpan ke document_chunks →
 * hitung embedding tiap chunk via Gemini → update kolom embedding.
 *
 * Semua progres dicatat ke ai_jobs (audit trail).
 */
class DocumentIndexer
{
    public function __construct(
        protected ChunkingService $chunker,
        protected EmbeddingService $embedder,
    ) {}

    /**
     * Indeks ulang dokumen: hapus chunk lama, buat ulang, embedding ulang.
     * Throw Exception bila gagal di langkah utama (caller bertanggung jawab
     * me-handle UI feedback).
     */
    public function index(Document $document): void
    {
        if (empty($document->extracted_text)) {
            throw new \RuntimeException('Dokumen tidak punya teks hasil ekstraksi.');
        }

        // Bersihkan chunk lama (re-index safe).
        DocumentChunk::where('document_id', $document->id)->delete();

        $this->runChunking($document);
        $this->runEmbedding($document);
    }

    /**
     * Tahap 1: chunking. Tulis chunks (tanpa embedding) ke DB dan log job.
     */
    protected function runChunking(Document $document): void
    {
        $job = AiJob::create([
            'document_id' => $document->id,
            'job_type' => AiJob::TYPE_CHUNK,
            'status' => AiJob::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $start = microtime(true);

        try {
            $chunks = $this->chunker->chunk($document->extracted_text);

            foreach ($chunks as $i => $content) {
                DocumentChunk::create([
                    'document_id' => $document->id,
                    'chunk_index' => $i,
                    'content' => $content,
                    'token_count' => $this->chunker->estimateTokens($content),
                ]);
            }

            $document->update(['total_chunks' => count($chunks)]);

            $job->update([
                'status' => AiJob::STATUS_COMPLETED,
                'finished_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
        } catch (Throwable $e) {
            $job->update([
                'status' => AiJob::STATUS_FAILED,
                'finished_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Tahap 2: embedding. Loop tiap chunk, panggil EmbeddingService, update
     * kolom embedding via raw SQL (karena format-nya bergantung driver DB).
     */
    protected function runEmbedding(Document $document): void
    {
        $job = AiJob::create([
            'document_id' => $document->id,
            'job_type' => AiJob::TYPE_EMBED,
            'status' => AiJob::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $start = microtime(true);
        $totalTokens = 0;
        $driver = DB::connection()->getDriverName();

        try {
            $chunks = DocumentChunk::where('document_id', $document->id)
                ->orderBy('chunk_index')
                ->get();

            foreach ($chunks as $chunk) {
                $vector = $this->embedder->embed($chunk->content);
                $totalTokens += $chunk->token_count ?? 0;

                $literal = self::vectorLiteral($vector, $driver);

                // Update via raw SQL: pgvector menerima literal string '[v1,v2,...]',
                // SQLite menerima TEXT JSON-encoded array.
                DB::update(
                    'UPDATE document_chunks SET embedding = ? WHERE id = ?',
                    [$literal, $chunk->id],
                );
            }

            $job->update([
                'status' => AiJob::STATUS_COMPLETED,
                'finished_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'tokens_used' => $totalTokens,
            ]);
        } catch (Throwable $e) {
            $job->update([
                'status' => AiJob::STATUS_FAILED,
                'finished_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Format vector menjadi string sesuai driver target.
     * - pgvector: '[1.0,2.0,3.0]' (akan otomatis di-cast ke type vector).
     * - sqlite : '[1.0,2.0,3.0]' juga, tapi disimpan sebagai TEXT.
     *
     * Format keduanya sengaja dibuat sama agar interop sederhana.
     */
    public static function vectorLiteral(array $vector, string $driver = 'pgsql'): string
    {
        return '['.implode(',', array_map(
            fn ($v) => rtrim(rtrim(sprintf('%.6F', (float) $v), '0'), '.'),
            $vector,
        )).']';
    }
}
