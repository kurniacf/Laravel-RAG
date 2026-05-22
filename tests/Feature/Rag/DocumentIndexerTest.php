<?php

use App\Models\AiJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Services\Rag\ChunkingService;
use App\Services\Rag\DocumentIndexer;
use App\Services\Rag\EmbeddingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('gemini.api_key', 'dummy-test-key');
});

function makeReadyDocument(string $text): Document
{
    $user = User::factory()->create();

    return Document::create([
        'user_id' => $user->id,
        'title' => 'Test doc',
        'original_filename' => 't.pdf',
        'file_path' => 'documents/'.$user->id.'/t.pdf',
        'file_size_bytes' => 1024,
        'status' => Document::STATUS_READY,
        'extracted_text' => $text,
        'page_count' => 1,
        'word_count' => str_word_count($text),
        'processed_at' => now(),
    ]);
}

function fakeEmbeddingResponse(): void
{
    $fakeVector = array_fill(0, 768, 0.1);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'embedding' => ['values' => $fakeVector],
        ], 200),
    ]);
}

test('indexer membuat chunk dan embedding untuk dokumen ready', function () {
    fakeEmbeddingResponse();

    // Teks ~3000 char akan menjadi minimal 2 chunk dengan default config.
    $text = str_repeat('Ini paragraf pendek tentang materi belajar. ', 80);

    $doc = makeReadyDocument($text);

    $indexer = new DocumentIndexer(
        new ChunkingService(),
        new EmbeddingService(),
    );

    $indexer->index($doc);

    $chunks = DocumentChunk::where('document_id', $doc->id)->orderBy('chunk_index')->get();

    expect($chunks->count())->toBeGreaterThanOrEqual(2);
    expect($doc->fresh()->total_chunks)->toBe($chunks->count());

    // Cek embedding ter-isi (tidak null) di semua chunk.
    foreach ($chunks as $chunk) {
        $row = DB::table('document_chunks')->where('id', $chunk->id)->first();
        expect($row->embedding)->not->toBeNull();
        // Format literal: dimulai '[' dan diakhiri ']'.
        expect($row->embedding)->toStartWith('[')->toEndWith(']');
    }
});

test('indexer menulis dua AiJob (chunk + embed) berstatus completed', function () {
    fakeEmbeddingResponse();

    $doc = makeReadyDocument('Teks singkat untuk diuji indexing chunk dan embed di service.');

    $indexer = new DocumentIndexer(new ChunkingService(), new EmbeddingService());
    $indexer->index($doc);

    $jobs = AiJob::where('document_id', $doc->id)->get();

    expect($jobs)->toHaveCount(2);
    expect($jobs->pluck('job_type')->all())->toEqualCanonicalizing([AiJob::TYPE_CHUNK, AiJob::TYPE_EMBED]);
    expect($jobs->pluck('status')->unique()->all())->toBe([AiJob::STATUS_COMPLETED]);

    // Tokens_used pada job embed harus terisi (akumulasi token chunk).
    $embedJob = $jobs->firstWhere('job_type', AiJob::TYPE_EMBED);
    expect($embedJob->tokens_used)->toBeGreaterThan(0);
});

test('indexer reset chunk lama saat re-index', function () {
    fakeEmbeddingResponse();

    $doc = makeReadyDocument('Teks awal yang akan diindeks ulang dengan pemanggilan berulang.');

    $indexer = new DocumentIndexer(new ChunkingService(), new EmbeddingService());
    $indexer->index($doc);
    $firstCount = DocumentChunk::where('document_id', $doc->id)->count();

    $indexer->index($doc);
    $secondCount = DocumentChunk::where('document_id', $doc->id)->count();

    expect($firstCount)->toBe($secondCount);
    // Total chunks tidak akumulatif.
    expect($doc->fresh()->total_chunks)->toBe($secondCount);
});

test('indexer menolak dokumen tanpa extracted_text', function () {
    $doc = makeReadyDocument('Lorem ipsum');
    $doc->update(['extracted_text' => null]);

    $indexer = new DocumentIndexer(new ChunkingService(), new EmbeddingService());

    expect(fn () => $indexer->index($doc->fresh()))
        ->toThrow(RuntimeException::class);
});

test('format vector literal cocok untuk pgvector', function () {
    $vector = [1.5, -0.25, 0.0, 3.141592];
    $literal = DocumentIndexer::vectorLiteral($vector);

    expect($literal)->toBe('[1.5,-0.25,0,3.141592]');
});
