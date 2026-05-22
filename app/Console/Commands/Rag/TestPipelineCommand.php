<?php

namespace App\Console\Commands\Rag;

use App\Models\AiJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\Rag\DocumentIndexer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Verifikasi end-to-end pipeline chunking + embedding dengan API Gemini asli.
 *
 *  Pemakaian:
 *      php artisan rag:test-pipeline             # cari dokumen ready & belum terindeks
 *      php artisan rag:test-pipeline {id}        # paksa dokumen tertentu
 *      php artisan rag:test-pipeline --reindex   # boleh ulang indexing meski sudah ada chunk
 */
#[Signature('rag:test-pipeline {document?} {--reindex : Paksa indexing ulang meski total_chunks > 0}')]
#[Description('Uji chunking + embedding ke Gemini secara nyata; melaporkan jumlah chunk, durasi, dan menjalankan similarity query.')]
class TestPipelineCommand extends Command
{
    public function handle(DocumentIndexer $indexer): int
    {
        if (empty(config('gemini.api_key'))) {
            $this->error('GEMINI_API_KEY belum diisi di .env. Lihat README seksi 8.');

            return self::FAILURE;
        }

        $document = $this->resolveDocument();

        if (! $document) {
            $this->error('Tidak ada dokumen ready yang dapat diuji.');
            $this->line('Tambah --reindex jika ingin meng-index ulang dokumen yang sudah ada chunk.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Dokumen target: #%d "%s" (user_id=%d, %d karakter teks)',
            $document->id,
            $document->title,
            $document->user_id,
            mb_strlen($document->extracted_text ?? ''),
        ));

        $start = microtime(true);

        try {
            $indexer->index($document);
        } catch (Throwable $e) {
            $this->error('Indexer gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $duration = (microtime(true) - $start) * 1000;

        $fresh = $document->fresh();
        $chunks = DocumentChunk::where('document_id', $fresh->id)->orderBy('chunk_index')->get();

        // Cek embedding terisi (kolom embedding di pgsql adalah tipe vector, tidak
        // bisa diakses langsung sebagai field Eloquent — pakai raw query).
        $withEmbedding = DB::table('document_chunks')
            ->where('document_id', $fresh->id)
            ->whereNotNull('embedding')
            ->count();

        $jobs = AiJob::where('document_id', $fresh->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $this->newLine();
        $this->info('Ringkasan hasil:');
        $this->table(['Metric', 'Nilai'], [
            ['Total chunks',                $chunks->count()],
            ['Chunks dengan embedding',     $withEmbedding],
            ['document.total_chunks',       $fresh->total_chunks],
            ['Total token (estimasi)',      $chunks->sum('token_count')],
            ['Durasi total (ms)',           number_format($duration, 0)],
            ['Panjang teks (karakter)',     mb_strlen($fresh->extracted_text ?? '')],
        ]);

        $this->newLine();
        $this->info('Riwayat AI jobs terbaru:');
        $this->table(['ID', 'job_type', 'status', 'duration_ms', 'tokens_used', 'error'], $jobs->map(fn ($j) => [
            $j->id,
            $j->job_type,
            $j->status,
            $j->duration_ms ?? '-',
            $j->tokens_used ?? '-',
            $j->error_message ? mb_substr($j->error_message, 0, 60).'...' : '-',
        ])->all());

        // ─────────── Similarity query uji ───────────
        if ($withEmbedding < 2 || DB::connection()->getDriverName() !== 'pgsql') {
            $this->newLine();
            $this->warn('Skip similarity test: butuh ≥ 2 chunks ber-embedding di PostgreSQL.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Uji similarity query: ambil 3 chunk paling mirip dengan chunk pertama dokumen ini.');

        $firstChunk = DB::table('document_chunks')
            ->where('document_id', $fresh->id)
            ->orderBy('chunk_index')
            ->first(['id', 'chunk_index', 'content', 'embedding']);

        $rows = DB::select(
            'SELECT id, chunk_index, LEFT(content, 80) AS preview, embedding <=> ? AS distance
             FROM document_chunks
             WHERE document_id = ?
             ORDER BY embedding <=> ?
             LIMIT 3',
            [$firstChunk->embedding, $fresh->id, $firstChunk->embedding],
        );

        $this->table(['rank', 'chunk_index', 'distance', 'preview'], collect($rows)->values()->map(fn ($r, $i) => [
            $i + 1,
            $r->chunk_index,
            sprintf('%.6f', (float) $r->distance),
            $r->preview,
        ])->all());

        $this->info('Pipeline embedding ke Gemini & similarity di pgvector: BERFUNGSI.');

        return self::SUCCESS;
    }

    /**
     * Pilih dokumen sumber: dari argumen atau yang status=ready dan total_chunks=0.
     */
    protected function resolveDocument(): ?Document
    {
        $id = $this->argument('document');

        if ($id !== null) {
            return Document::find((int) $id);
        }

        return Document::query()
            ->where('status', Document::STATUS_READY)
            ->whereNotNull('extracted_text')
            ->when(! $this->option('reindex'), fn ($q) => $q->where('total_chunks', 0))
            ->orderBy('id')
            ->first();
    }
}
