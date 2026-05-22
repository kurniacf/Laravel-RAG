<?php

namespace App\Console\Commands\Rag;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Services\Rag\DocumentIndexer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Verifikasi manual: pastikan pgvector similarity search bekerja.
 * Membuat dokumen dummy + 3 chunk dengan vector buatan, lalu menjalankan
 * query similarity untuk membuktikan ordering hasil sesuai harapan.
 */
#[Signature('rag:verify-similarity {--keep : Jangan hapus data dummy setelah selesai}')]
#[Description('Verifikasi pgvector similarity search dengan vector buatan (sanity check Fase 6)')]
class VerifySimilarityCommand extends Command
{
    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->error('Command ini hanya berjalan di PostgreSQL (butuh pgvector).');

            return self::FAILURE;
        }

        $this->info('Menyiapkan dokumen dan chunk dummy...');

        $user = User::firstOrCreate(
            ['email' => 'rag-verify@pintarbelajar.test'],
            ['name' => 'RAG Verifier', 'password' => bcrypt('dummy'), 'role' => User::ROLE_USER, 'email_verified_at' => now()],
        );

        $doc = Document::create([
            'user_id' => $user->id,
            'title' => '[VERIFY] Dummy untuk uji similarity',
            'original_filename' => 'verify.pdf',
            'file_path' => 'documents/verify/dummy.pdf',
            'file_size_bytes' => 100,
            'status' => Document::STATUS_READY,
            'extracted_text' => 'placeholder',
        ]);

        $chunks = [
            ['idx' => 0, 'content' => 'Topik A (sangat mirip query)',  'vec' => $this->axisVector(0)],
            ['idx' => 1, 'content' => 'Topik B (tidak mirip)',         'vec' => $this->axisVector(1)],
            ['idx' => 2, 'content' => 'Topik A varian (cukup mirip)',  'vec' => $this->normalize([0.95, 0.05, 0.05])],
        ];

        foreach ($chunks as $c) {
            $chunk = DocumentChunk::create([
                'document_id' => $doc->id,
                'chunk_index' => $c['idx'],
                'content' => $c['content'],
                'token_count' => 5,
            ]);

            DB::update(
                'UPDATE document_chunks SET embedding = ? WHERE id = ?',
                [DocumentIndexer::vectorLiteral($c['vec']), $chunk->id],
            );
        }

        $this->newLine();
        $this->info('Menjalankan similarity query (cosine distance) dengan vector axis-0...');

        $queryVec = DocumentIndexer::vectorLiteral($this->axisVector(0));

        $rows = DB::select(
            'SELECT chunk_index, content, embedding <=> ?::vector AS distance
             FROM document_chunks
             WHERE document_id = ?
             ORDER BY embedding <=> ?::vector
             LIMIT 3',
            [$queryVec, $doc->id, $queryVec],
        );

        $this->table(
            ['Rank', 'chunk_index', 'cosine_distance', 'content'],
            collect($rows)->values()->map(fn ($r, $i) => [
                $i + 1,
                $r->chunk_index,
                sprintf('%.6f', (float) $r->distance),
                $r->content,
            ])->all(),
        );

        // Cek ordering yang diharapkan: 0 → 2 → 1.
        $ordering = array_map(fn ($r) => (int) $r->chunk_index, $rows);
        $expected = [0, 2, 1];

        if ($ordering === $expected) {
            $this->info('OK: ordering sesuai harapan (0 → 2 → 1). pgvector similarity search berfungsi.');
            $status = self::SUCCESS;
        } else {
            $this->error('GAGAL: ordering tidak sesuai harapan.');
            $this->line('Expected: '.implode(' → ', $expected));
            $this->line('Actual  : '.implode(' → ', $ordering));
            $status = self::FAILURE;
        }

        if (! $this->option('keep')) {
            $this->newLine();
            $this->info('Membersihkan data dummy...');
            $doc->delete();
            $user->delete();
        } else {
            $this->newLine();
            $this->warn('Data dummy dipertahankan (flag --keep).');
            $this->line("  user_id={$user->id}, document_id={$doc->id}");
        }

        return $status;
    }

    /**
     * Vector unit di axis tertentu (sisanya nol).
     *
     * @return array<int, float>
     */
    private function axisVector(int $axis): array
    {
        $v = array_fill(0, 768, 0.0);
        $v[$axis] = 1.0;

        return $v;
    }

    /**
     * Buat vector dari beberapa komponen awal (sisanya nol), lalu normalisasi.
     *
     * @param  array<int, float>  $components
     * @return array<int, float>
     */
    private function normalize(array $components): array
    {
        $v = array_fill(0, 768, 0.0);
        foreach ($components as $i => $val) {
            $v[$i] = (float) $val;
        }

        $sumSq = 0.0;
        foreach ($v as $val) {
            $sumSq += $val * $val;
        }
        $norm = sqrt($sumSq);

        if ($norm < 1e-9) {
            return $v;
        }

        foreach ($v as $i => $val) {
            $v[$i] = $val / $norm;
        }

        return $v;
    }
}
