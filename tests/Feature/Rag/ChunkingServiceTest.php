<?php

use App\Services\Rag\ChunkingService;

test('text pendek menghasilkan satu chunk', function () {
    $svc = new ChunkingService();

    $chunks = $svc->chunk('Halo dunia.');

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0])->toBe('Halo dunia.');
});

test('text kosong menghasilkan array kosong', function () {
    $svc = new ChunkingService();

    expect($svc->chunk(''))->toBe([]);
    expect($svc->chunk('   '))->toBe([]);
});

test('text panjang dipecah menjadi beberapa chunk dengan overlap', function () {
    $svc = new ChunkingService(chunkChars: 100, overlapChars: 20);

    // 350 karakter (di luar batas chunk 100).
    $text = str_repeat('A B C D E F G H I J ', 35);

    $chunks = $svc->chunk($text);

    expect(count($chunks))->toBeGreaterThan(2);

    // Tiap chunk tidak melebihi chunkChars.
    foreach ($chunks as $chunk) {
        expect(mb_strlen($chunk))->toBeLessThanOrEqual(100);
    }
});

test('estimasi token roughly 1 token per 4 karakter', function () {
    $svc = new ChunkingService();

    expect($svc->estimateTokens('abcdefgh'))->toBe(2);
    expect($svc->estimateTokens(''))->toBe(0);
});
