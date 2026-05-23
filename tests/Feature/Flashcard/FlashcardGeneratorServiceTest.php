<?php

use App\Models\AiJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Flashcard;
use App\Models\User;
use App\Services\Flashcard\FlashcardGeneratorService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('gemini.api_key', 'dummy-test-key');
});

/** Dokumen ready dengan chunk siap dijadikan sumber flashcard. */
function makeFlashcardDocument(int $chunkCount = 5): Document
{
    $user = User::factory()->create();

    $doc = Document::create([
        'user_id' => $user->id,
        'title' => 'Materi Flashcard',
        'original_filename' => 'fc.pdf',
        'file_path' => 'documents/'.$user->id.'/fc.pdf',
        'file_size_bytes' => 4096,
        'status' => Document::STATUS_READY,
        'extracted_text' => 'Teks materi lengkap.',
        'total_chunks' => $chunkCount,
        'processed_at' => now(),
    ]);

    for ($i = 0; $i < $chunkCount; $i++) {
        DocumentChunk::create([
            'document_id' => $doc->id,
            'chunk_index' => $i,
            'content' => 'Konten chunk '.$i.' membahas konsep penting.',
            'token_count' => 20,
        ]);
    }

    return $doc;
}

/** Bungkus teks model ke struktur respons Gemini generateContent. */
function flashcardGeminiBody(string $text): array
{
    return [
        'candidates' => [[
            'content' => ['parts' => [['text' => $text]]],
            'finishReason' => 'STOP',
        ]],
        'usageMetadata' => ['totalTokenCount' => 90],
    ];
}

/** Lima flashcard valid. */
function validFlashcardCards(): array
{
    return [
        ['front_text' => 'Fotosintesis', 'back_text' => 'Proses tumbuhan mengubah cahaya menjadi energi kimia.', 'difficulty' => 'medium', 'source_chunk' => 1],
        ['front_text' => 'Klorofil', 'back_text' => 'Pigmen hijau daun yang menangkap cahaya.', 'difficulty' => 'easy', 'source_chunk' => 2],
        ['front_text' => 'Stomata', 'back_text' => 'Pori pada daun untuk pertukaran gas.', 'difficulty' => 'hard', 'source_chunk' => 3],
        ['front_text' => 'Respirasi', 'back_text' => 'Proses pelepasan energi dari makanan.', 'difficulty' => 'medium', 'source_chunk' => 4],
        ['front_text' => 'Xilem', 'back_text' => 'Jaringan pengangkut air pada tumbuhan.', 'difficulty' => 'medium', 'source_chunk' => 5],
    ];
}

function fakeFlashcardResponse(string $modelText): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(flashcardGeminiBody($modelText), 200),
    ]);
}

test('generate membuat flashcard dari JSON valid', function () {
    fakeFlashcardResponse(json_encode(['flashcards' => validFlashcardCards()]));
    $doc = makeFlashcardDocument();

    $cards = app(FlashcardGeneratorService::class)->generate($doc, 5);

    expect($cards)->toHaveCount(5);
    expect(Flashcard::where('document_id', $doc->id)->count())->toBe(5);

    $first = Flashcard::where('document_id', $doc->id)->orderBy('position')->first();
    expect($first->front_text)->toBe('Fotosintesis');
    expect($first->back_text)->not->toBe('');
    expect($first->source_chunk_id)->not->toBeNull();
});

test('generate mencatat AiJob flashcard_gen berstatus completed', function () {
    fakeFlashcardResponse(json_encode(['flashcards' => validFlashcardCards()]));
    $doc = makeFlashcardDocument();

    app(FlashcardGeneratorService::class)->generate($doc, 5);

    $job = AiJob::where('document_id', $doc->id)
        ->where('job_type', AiJob::TYPE_FLASHCARD_GEN)
        ->first();

    expect($job)->not->toBeNull();
    expect($job->status)->toBe(AiJob::STATUS_COMPLETED);
});

test('generate tetap mem-parse JSON walau dibungkus markdown fence', function () {
    $json = json_encode(['flashcards' => validFlashcardCards()]);
    fakeFlashcardResponse("```json\n".$json."\n```");
    $doc = makeFlashcardDocument();

    $cards = app(FlashcardGeneratorService::class)->generate($doc, 5);

    expect($cards)->toHaveCount(5);
});

test('generate melakukan retry saat JSON pertama gagal di-parse', function () {
    Http::fakeSequence()
        ->push(flashcardGeminiBody('maaf, ini bukan JSON'), 200)
        ->push(flashcardGeminiBody(json_encode(['flashcards' => validFlashcardCards()])), 200);
    $doc = makeFlashcardDocument();

    $cards = app(FlashcardGeneratorService::class)->generate($doc, 5);

    expect($cards)->toHaveCount(5);
});

test('generate gagal dan menandai AiJob failed setelah JSON terus tidak valid', function () {
    fakeFlashcardResponse('bukan JSON sama sekali');
    $doc = makeFlashcardDocument();

    expect(fn () => app(FlashcardGeneratorService::class)->generate($doc, 5))
        ->toThrow(RuntimeException::class);

    $job = AiJob::where('document_id', $doc->id)
        ->where('job_type', AiJob::TYPE_FLASHCARD_GEN)
        ->first();
    expect($job->status)->toBe(AiJob::STATUS_FAILED);
});

test('generate membuang kartu yang strukturnya tidak valid', function () {
    $cards = validFlashcardCards();
    $cards[] = ['front_text' => '', 'back_text' => 'tanpa sisi depan', 'difficulty' => 'medium', 'source_chunk' => 1];
    fakeFlashcardResponse(json_encode(['flashcards' => $cards]));
    $doc = makeFlashcardDocument();

    app(FlashcardGeneratorService::class)->generate($doc, 20);

    // Lima valid disimpan, satu rusak (front_text kosong) dibuang.
    expect(Flashcard::where('document_id', $doc->id)->count())->toBe(5);
});

test('generate menolak dokumen tanpa chunk', function () {
    $doc = makeFlashcardDocument(0);

    expect(fn () => app(FlashcardGeneratorService::class)->generate($doc, 5))
        ->toThrow(RuntimeException::class);
});

test('generate kedua kali menambah kartu tanpa menghapus yang lama', function () {
    fakeFlashcardResponse(json_encode(['flashcards' => validFlashcardCards()]));
    $doc = makeFlashcardDocument();
    $service = app(FlashcardGeneratorService::class);

    $service->generate($doc, 5);
    $service->generate($doc, 5);

    expect(Flashcard::where('document_id', $doc->id)->count())->toBe(10);

    $positions = Flashcard::where('document_id', $doc->id)->orderBy('position')->pluck('position');
    expect($positions->first())->toBe(0);
    expect($positions->last())->toBe(9);
});
