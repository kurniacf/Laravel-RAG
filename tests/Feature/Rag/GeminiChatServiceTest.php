<?php

use App\Services\Rag\GeminiChatService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('gemini.api_key', 'dummy-test-key');
});

/** Bungkus teks ke struktur respons Gemini generateContent. */
function geminiChatBody(string $text): array
{
    return [
        'candidates' => [[
            'content' => ['parts' => [['text' => $text]]],
            'finishReason' => 'STOP',
        ]],
        'usageMetadata' => ['totalTokenCount' => 10],
    ];
}

test('generate mengembalikan jawaban saat panggilan sukses', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiChatBody('halo'), 200)]);

    $result = app(GeminiChatService::class)->generate('sistem', 'pesan');

    expect($result['answer'])->toBe('halo');
});

test('generate mencoba ulang saat Gemini balas 503 lalu berhasil', function () {
    // 503 transien di percobaan pertama, sukses di percobaan kedua.
    Http::fakeSequence()
        ->push('{"error":{"code":503,"status":"UNAVAILABLE"}}', 503)
        ->push(geminiChatBody('berhasil setelah retry'), 200);

    $result = app(GeminiChatService::class)->generate('sistem', 'pesan');

    expect($result['answer'])->toBe('berhasil setelah retry');
});

test('generate gagal dengan pesan jelas bila 503 terus-menerus', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response('sibuk', 503)]);

    expect(fn () => app(GeminiChatService::class)->generate('sistem', 'pesan'))
        ->toThrow(RuntimeException::class);
});

test('generate tidak mengulang untuk error permanen seperti 400', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response('bad request', 400)]);

    expect(fn () => app(GeminiChatService::class)->generate('sistem', 'pesan'))
        ->toThrow(RuntimeException::class);

    // 400 bukan error transien — hanya satu kali request, tanpa retry.
    Http::assertSentCount(1);
});
