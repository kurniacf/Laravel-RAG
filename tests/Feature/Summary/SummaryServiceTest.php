<?php

use App\Models\AiJob;
use App\Models\Document;
use App\Models\Summary;
use App\Models\User;
use App\Services\Summary\SummaryService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('gemini.api_key', 'dummy-test-key');
});

/**
 * Dokumen siap-ringkas: status ready + punya extracted_text.
 */
function makeSummarizableDocument(string $text = 'Materi tentang fotosintesis dan respirasi tumbuhan.'): Document
{
    $user = User::factory()->create();

    return Document::create([
        'user_id' => $user->id,
        'title' => 'Biologi Bab 3',
        'original_filename' => 'bio.pdf',
        'file_path' => 'documents/'.$user->id.'/bio.pdf',
        'file_size_bytes' => 2048,
        'status' => Document::STATUS_READY,
        'extracted_text' => $text,
        'page_count' => 5,
        'word_count' => str_word_count($text),
        'processed_at' => now(),
    ]);
}

/**
 * Fake respons Gemini generateContent dengan teks ringkasan tertentu.
 */
function fakeGeminiSummary(string $text = 'Ini ringkasan dummy dari materi.'): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => $text]]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['totalTokenCount' => 64],
        ], 200),
    ]);
}

test('generateAll menghasilkan tiga tipe ringkasan tersimpan di database', function () {
    fakeGeminiSummary();
    $doc = makeSummarizableDocument();

    $summaries = app(SummaryService::class)->generateAll($doc);

    expect($summaries)->toHaveCount(3);
    expect(Summary::where('document_id', $doc->id)->pluck('type')->all())
        ->toEqualCanonicalizing([
            Summary::TYPE_EXECUTIVE,
            Summary::TYPE_PER_CHAPTER,
            Summary::TYPE_KEY_POINTS,
        ]);
});

test('generateAll mengisi metadata ringkasan (word_count, tokens_used, model_used)', function () {
    fakeGeminiSummary('Fotosintesis mengubah cahaya menjadi energi kimia.');
    $doc = makeSummarizableDocument();

    app(SummaryService::class)->generateAll($doc);

    $summary = Summary::where('document_id', $doc->id)->first();
    expect($summary->word_count)->toBe(6);
    expect($summary->tokens_used)->toBe(64);
    expect($summary->model_used)->toBe(config('gemini.model'));
});

test('generateAll mencatat satu AiJob summarize berstatus completed', function () {
    fakeGeminiSummary();
    $doc = makeSummarizableDocument();

    app(SummaryService::class)->generateAll($doc);

    $jobs = AiJob::where('document_id', $doc->id)
        ->where('job_type', AiJob::TYPE_SUMMARIZE)
        ->get();

    expect($jobs)->toHaveCount(1);
    expect($jobs->first()->status)->toBe(AiJob::STATUS_COMPLETED);
    // Total token = akumulasi tiga panggilan generate.
    expect($jobs->first()->tokens_used)->toBe(64 * 3);
    expect($jobs->first()->duration_ms)->not->toBeNull();
});

test('generateAll yang dipanggil dua kali me-replace ringkasan, bukan menduplikasi', function () {
    fakeGeminiSummary();
    $doc = makeSummarizableDocument();

    $service = app(SummaryService::class);
    $service->generateAll($doc);
    $service->generateAll($doc);

    expect(Summary::where('document_id', $doc->id)->count())->toBe(3);
});

test('generateAll menolak dokumen tanpa extracted_text', function () {
    fakeGeminiSummary();
    $doc = makeSummarizableDocument();
    $doc->update(['extracted_text' => null]);

    expect(fn () => app(SummaryService::class)->generateAll($doc->fresh()))
        ->toThrow(RuntimeException::class);
});

test('generateAll menandai AiJob gagal bila panggilan Gemini error', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response('server error', 500),
    ]);
    $doc = makeSummarizableDocument();

    expect(fn () => app(SummaryService::class)->generateAll($doc))
        ->toThrow(RuntimeException::class);

    $job = AiJob::where('document_id', $doc->id)
        ->where('job_type', AiJob::TYPE_SUMMARIZE)
        ->first();
    expect($job->status)->toBe(AiJob::STATUS_FAILED);
    expect($job->error_message)->not->toBeNull();
});
