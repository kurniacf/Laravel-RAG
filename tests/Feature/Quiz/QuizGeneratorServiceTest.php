<?php

use App\Models\AiJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Quiz\QuizGeneratorService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('gemini.api_key', 'dummy-test-key');
});

/**
 * Dokumen ready dengan sejumlah chunk siap dijadikan sumber soal.
 */
function makeQuizDocument(int $chunkCount = 4): Document
{
    $user = User::factory()->create();

    $doc = Document::create([
        'user_id' => $user->id,
        'title' => 'Materi Kuis',
        'original_filename' => 'kuis.pdf',
        'file_path' => 'documents/'.$user->id.'/kuis.pdf',
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
            'content' => 'Konten chunk '.$i.' membahas konsep penting dalam materi.',
            'token_count' => 20,
        ]);
    }

    return $doc;
}

/**
 * Bungkus teks model ke dalam struktur respons Gemini generateContent.
 */
function quizGeminiBody(string $text): array
{
    return [
        'candidates' => [[
            'content' => ['parts' => [['text' => $text]]],
            'finishReason' => 'STOP',
        ]],
        'usageMetadata' => ['totalTokenCount' => 120],
    ];
}

/**
 * Tiga soal valid: satu per tipe.
 */
function validQuizQuestions(): array
{
    return [
        [
            'type' => 'mcq',
            'question_text' => 'Apa fungsi utama konsep tersebut?',
            'options' => ['Pilihan A', 'Pilihan B', 'Pilihan C', 'Pilihan D'],
            'correct_answer' => 'Pilihan B',
            'explanation' => 'Karena materi menyebut Pilihan B sebagai fungsi utama.',
            'difficulty' => 'medium',
            'source_chunk' => 1,
        ],
        [
            'type' => 'true_false',
            'question_text' => 'Pernyataan ini sesuai materi.',
            'correct_answer' => 'Benar',
            'explanation' => 'Materi menyatakan hal yang sama.',
            'difficulty' => 'medium',
            'source_chunk' => 2,
        ],
        [
            'type' => 'short_answer',
            'question_text' => 'Sebutkan konsep inti materi.',
            'correct_answer' => 'konsep inti',
            'explanation' => 'Disebut langsung di materi.',
            'difficulty' => 'medium',
            'source_chunk' => 3,
        ],
    ];
}

function fakeQuizResponse(string $modelText): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(quizGeminiBody($modelText), 200),
    ]);
}

test('generate membuat quiz dengan soal dari JSON valid', function () {
    fakeQuizResponse(json_encode(['questions' => validQuizQuestions()]));
    $doc = makeQuizDocument();

    $quiz = app(QuizGeneratorService::class)->generate($doc, 3, Quiz::DIFFICULTY_MEDIUM);

    expect($quiz->question_count)->toBe(3);
    expect($quiz->questions)->toHaveCount(3);
    expect($quiz->difficulty)->toBe(Quiz::DIFFICULTY_MEDIUM);

    $mcq = $quiz->questions->firstWhere('type', QuizQuestion::TYPE_MCQ);
    expect($mcq->options)->toHaveCount(4);
    expect($mcq->options->where('is_correct', true))->toHaveCount(1);
    expect($mcq->source_chunk_id)->not->toBeNull();

    $tf = $quiz->questions->firstWhere('type', QuizQuestion::TYPE_TRUE_FALSE);
    expect($tf->options)->toHaveCount(2);
    expect($tf->options->where('is_correct', true))->toHaveCount(1);

    $sa = $quiz->questions->firstWhere('type', QuizQuestion::TYPE_SHORT_ANSWER);
    expect($sa->options)->toHaveCount(0);
    expect($sa->correct_answer)->toBe('konsep inti');
});

test('generate mencatat AiJob quiz_gen berstatus completed', function () {
    fakeQuizResponse(json_encode(['questions' => validQuizQuestions()]));
    $doc = makeQuizDocument();

    app(QuizGeneratorService::class)->generate($doc, 3, Quiz::DIFFICULTY_MEDIUM);

    $job = AiJob::where('document_id', $doc->id)
        ->where('job_type', AiJob::TYPE_QUIZ_GEN)
        ->first();

    expect($job)->not->toBeNull();
    expect($job->status)->toBe(AiJob::STATUS_COMPLETED);
});

test('generate tetap mem-parse JSON walau dibungkus markdown fence', function () {
    $json = json_encode(['questions' => validQuizQuestions()]);
    fakeQuizResponse("```json\n".$json."\n```");
    $doc = makeQuizDocument();

    $quiz = app(QuizGeneratorService::class)->generate($doc, 3, Quiz::DIFFICULTY_MEDIUM);

    expect($quiz->questions)->toHaveCount(3);
});

test('generate melakukan retry saat JSON pertama gagal di-parse', function () {
    Http::fakeSequence()
        ->push(quizGeminiBody('maaf, ini bukan JSON sama sekali'), 200)
        ->push(quizGeminiBody(json_encode(['questions' => validQuizQuestions()])), 200);
    $doc = makeQuizDocument();

    $quiz = app(QuizGeneratorService::class)->generate($doc, 3, Quiz::DIFFICULTY_MEDIUM);

    expect($quiz->questions)->toHaveCount(3);
});

test('generate gagal dan menandai AiJob failed setelah tiga percobaan JSON tidak valid', function () {
    fakeQuizResponse('bukan JSON sama sekali');
    $doc = makeQuizDocument();

    expect(fn () => app(QuizGeneratorService::class)->generate($doc, 3, Quiz::DIFFICULTY_MEDIUM))
        ->toThrow(RuntimeException::class);

    $job = AiJob::where('document_id', $doc->id)
        ->where('job_type', AiJob::TYPE_QUIZ_GEN)
        ->first();
    expect($job->status)->toBe(AiJob::STATUS_FAILED);
});

test('generate membuang soal yang strukturnya tidak valid', function () {
    $questions = validQuizQuestions();
    // Soal mcq rusak: correct_answer tidak ada di antara options.
    $questions[] = [
        'type' => 'mcq',
        'question_text' => 'Soal rusak.',
        'options' => ['A', 'B', 'C', 'D'],
        'correct_answer' => 'Z (di luar opsi)',
        'explanation' => 'x',
        'difficulty' => 'medium',
        'source_chunk' => 1,
    ];
    fakeQuizResponse(json_encode(['questions' => $questions]));
    $doc = makeQuizDocument();

    $quiz = app(QuizGeneratorService::class)->generate($doc, 10, Quiz::DIFFICULTY_MEDIUM);

    // Tiga valid disimpan, satu rusak dibuang.
    expect($quiz->questions)->toHaveCount(3);
});

test('generate menolak dokumen tanpa chunk', function () {
    $doc = makeQuizDocument(0);

    expect(fn () => app(QuizGeneratorService::class)->generate($doc, 3, Quiz::DIFFICULTY_MEDIUM))
        ->toThrow(RuntimeException::class);
});
