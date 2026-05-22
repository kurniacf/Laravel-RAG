<?php

use App\Services\Rag\EmbeddingService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('gemini.api_key', 'dummy-test-key');
    config()->set('gemini.embedding_model', 'text-embedding-001');
    config()->set('gemini.embedding_dimensions', 768);
    config()->set('gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
});

test('mengembalikan array 768 float untuk teks input', function () {
    $fakeVector = array_fill(0, 768, 0.1);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'embedding' => ['values' => $fakeVector],
        ], 200),
    ]);

    $svc = new EmbeddingService();
    $vector = $svc->embed('Halo dunia');

    expect($vector)->toBeArray()->toHaveCount(768);
    expect($vector[0])->toBeFloat()->toBe(0.1);
});

test('throw exception bila response tidak punya 768 dimensi', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'embedding' => ['values' => [1.0, 2.0]], // dimensi salah
        ], 200),
    ]);

    $svc = new EmbeddingService();

    expect(fn () => $svc->embed('x'))->toThrow(RuntimeException::class);
});

test('throw exception bila API key kosong', function () {
    config()->set('gemini.api_key', '');

    $svc = new EmbeddingService();

    expect(fn () => $svc->embed('x'))
        ->toThrow(RuntimeException::class, 'GEMINI_API_KEY');
});

test('throw exception saat HTTP gagal (4xx atau 5xx)', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    $svc = new EmbeddingService();

    expect(fn () => $svc->embed('x'))
        ->toThrow(RuntimeException::class, 'HTTP 401');
});

test('embed mencoba ulang saat server balas 503 lalu berhasil', function () {
    $fakeVector = array_fill(0, 768, 0.2);

    // 503 transien di percobaan pertama, sukses di percobaan kedua.
    Http::fakeSequence()
        ->push('{"error":{"code":503,"status":"UNAVAILABLE"}}', 503)
        ->push(['embedding' => ['values' => $fakeVector]], 200);

    $vector = (new EmbeddingService())->embed('teks uji');

    expect($vector)->toHaveCount(768);
});
