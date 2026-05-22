<?php

use App\Models\Summary;

/**
 * Test parsing konten ringkasan menjadi struktur yang siap dirender.
 * Tidak menyentuh database — cukup instance Summary dengan content.
 */

test('sections memecah konten per-bagian berdasarkan heading ###', function () {
    $summary = new Summary([
        'content' => "### Bagian Satu\nIsi bagian satu.\n\n### Bagian Dua\nIsi bagian dua.",
    ]);

    $sections = $summary->sections();

    expect($sections)->toHaveCount(2);
    expect($sections[0]['title'])->toBe('Bagian Satu');
    expect($sections[0]['body'])->toBe('Isi bagian satu.');
    expect($sections[1]['title'])->toBe('Bagian Dua');
    expect($sections[1]['body'])->toBe('Isi bagian dua.');
});

test('sections mengenali heading bertanda tebal **judul**', function () {
    $summary = new Summary([
        'content' => "**Pendahuluan**\nKalimat pembuka materi.\n\n**Penutup**\nKalimat penutup materi.",
    ]);

    $sections = $summary->sections();

    expect($sections)->toHaveCount(2);
    expect($sections[0]['title'])->toBe('Pendahuluan');
    expect($sections[1]['title'])->toBe('Penutup');
});

test('sections mengembalikan satu bagian tanpa judul bila tidak ada heading', function () {
    $summary = new Summary([
        'content' => 'Hanya paragraf biasa tanpa subjudul apa pun di dalamnya.',
    ]);

    $sections = $summary->sections();

    expect($sections)->toHaveCount(1);
    expect($sections[0]['title'])->toBe('');
    expect($sections[0]['body'])->toBe('Hanya paragraf biasa tanpa subjudul apa pun di dalamnya.');
});

test('sections menggabungkan beberapa baris isi di bawah satu heading', function () {
    $summary = new Summary([
        'content' => "### Topik\nBaris pertama.\nBaris kedua.",
    ]);

    $sections = $summary->sections();

    expect($sections)->toHaveCount(1);
    expect($sections[0]['body'])->toBe("Baris pertama.\nBaris kedua.");
});

test('points memecah daftar poin dan membuang penanda bullet', function () {
    $summary = new Summary([
        'content' => "- Poin pertama\n- Poin kedua\n- Poin ketiga",
    ]);

    expect($summary->points())->toBe(['Poin pertama', 'Poin kedua', 'Poin ketiga']);
});

test('points menerima penanda bernomor dan bullet lain', function () {
    $summary = new Summary([
        'content' => "1. Poin satu\n2) Poin dua\n• Poin tiga",
    ]);

    expect($summary->points())->toBe(['Poin satu', 'Poin dua', 'Poin tiga']);
});

test('points mengabaikan baris kosong', function () {
    $summary = new Summary([
        'content' => "- Poin A\n\n- Poin B\n   \n- Poin C",
    ]);

    expect($summary->points())->toBe(['Poin A', 'Poin B', 'Poin C']);
});
