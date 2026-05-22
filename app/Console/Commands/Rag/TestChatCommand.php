<?php

namespace App\Console\Commands\Rag;

use App\Models\Document;
use App\Services\Rag\RagService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Smoke test untuk RagService:
 *  php artisan rag:test-chat 1 "Apa itu turunan?"
 */
#[Signature('rag:test-chat {document : ID dokumen ter-vektorisasi} {question : Pertanyaan untuk diajukan}')]
#[Description('Ajukan satu pertanyaan ke RagService dan print jawaban + citation. Memanggil Gemini API asli.')]
class TestChatCommand extends Command
{
    public function handle(RagService $rag): int
    {
        $documentId = (int) $this->argument('document');
        $question = (string) $this->argument('question');

        $document = Document::find($documentId);
        if (! $document) {
            $this->error("Dokumen #{$documentId} tidak ditemukan.");

            return self::FAILURE;
        }
        if ($document->total_chunks === 0) {
            $this->error('Dokumen belum ter-vektorisasi. Jalankan php artisan rag:test-pipeline '.$documentId.' dulu.');

            return self::FAILURE;
        }

        $this->info(sprintf('Dokumen: #%d "%s"', $document->id, $document->title));
        $this->info("Pertanyaan: {$question}");
        $this->newLine();

        $start = microtime(true);

        try {
            $result = $rag->ask($document, $question);
        } catch (Throwable $e) {
            $this->error('RagService gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $duration = (microtime(true) - $start) * 1000;

        $this->line('<fg=green;options=bold>Jawaban:</> ');
        $this->line($result['answer']);
        $this->newLine();

        $this->line('<fg=blue>Cited chunk IDs:</> '.implode(', ', $result['cited_chunk_ids']));
        $this->line('<fg=blue>Tokens used:</>    '.($result['tokens_used'] ?? 'n/a'));
        $this->line('<fg=blue>Durasi:</>          '.number_format($duration, 0).' ms');

        return self::SUCCESS;
    }
}
