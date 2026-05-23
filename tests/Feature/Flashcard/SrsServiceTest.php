<?php

use App\Models\Document;
use App\Models\Flashcard;
use App\Models\User;
use App\Services\Flashcard\SrsService;

/** Buat satu flashcard (beserta dokumen & pemiliknya). */
function makeSrsFlashcard(?Document $document = null, int $position = 0): Flashcard
{
    if ($document === null) {
        $owner = User::factory()->create();
        $document = Document::create([
            'user_id' => $owner->id,
            'title' => 'Materi SRS',
            'original_filename' => 'srs.pdf',
            'file_path' => 'documents/'.$owner->id.'/srs.pdf',
            'file_size_bytes' => 1024,
            'status' => Document::STATUS_READY,
            'total_chunks' => 3,
        ]);
    }

    return Flashcard::create([
        'document_id' => $document->id,
        'user_id' => $document->user_id,
        'front_text' => 'Istilah '.$position,
        'back_text' => 'Definisi '.$position,
        'difficulty' => 'medium',
        'position' => $position,
    ]);
}

test('review kartu baru rating Mudah memberi repetisi 1 dan interval 1', function () {
    $card = makeSrsFlashcard();

    $review = app(SrsService::class)->review($card, $card->user, SrsService::QUALITY_EASY);

    expect($review->repetitions)->toBe(1);
    expect($review->interval_days)->toBe(1);
    expect($review->ease_factor)->toBeGreaterThan(2.5); // kualitas 5 menaikkan EF
    expect($review->next_review_at)->not->toBeNull();
});

test('dua review sukses berturut-turut memberi interval 6 hari', function () {
    $card = makeSrsFlashcard();
    $srs = app(SrsService::class);

    $srs->review($card, $card->user, SrsService::QUALITY_GOOD);          // rep 1, interval 1
    $review = $srs->review($card, $card->user, SrsService::QUALITY_GOOD); // rep 2, interval 6

    expect($review->repetitions)->toBe(2);
    expect($review->interval_days)->toBe(6);
});

test('review rating Sulit mereset repetisi dan interval', function () {
    $card = makeSrsFlashcard();
    $srs = app(SrsService::class);

    $srs->review($card, $card->user, SrsService::QUALITY_GOOD);
    $srs->review($card, $card->user, SrsService::QUALITY_GOOD); // interval 6
    $review = $srs->review($card, $card->user, SrsService::QUALITY_HARD);

    expect($review->repetitions)->toBe(0);
    expect($review->interval_days)->toBe(1);
});

test('kartu dinilai Sulit dijadwalkan lebih cepat daripada kartu dinilai Mudah', function () {
    $srs = app(SrsService::class);

    $hard = makeSrsFlashcard();
    $srs->review($hard, $hard->user, SrsService::QUALITY_GOOD);
    $srs->review($hard, $hard->user, SrsService::QUALITY_GOOD);
    $hardReview = $srs->review($hard, $hard->user, SrsService::QUALITY_HARD);

    $easy = makeSrsFlashcard();
    $srs->review($easy, $easy->user, SrsService::QUALITY_GOOD);
    $srs->review($easy, $easy->user, SrsService::QUALITY_GOOD);
    $easyReview = $srs->review($easy, $easy->user, SrsService::QUALITY_EASY);

    expect($hardReview->interval_days)->toBeLessThan($easyReview->interval_days);
});

test('ease factor tidak turun di bawah batas 1.3', function () {
    $card = makeSrsFlashcard();
    $srs = app(SrsService::class);

    $review = null;
    for ($i = 0; $i < 10; $i++) {
        $review = $srs->review($card, $card->user, 0); // kualitas terburuk berulang
    }

    expect($review->ease_factor)->toBeGreaterThanOrEqual(1.3);
});

test('dueCards hanya mengembalikan kartu yang jatuh tempo', function () {
    $card = makeSrsFlashcard();
    $document = $card->document;
    $user = $card->user;
    makeSrsFlashcard($document, 1);
    makeSrsFlashcard($document, 2);

    $srs = app(SrsService::class);

    // Tiga kartu, belum ada yang direview → semua jatuh tempo.
    expect($srs->dueCards($document, $user))->toHaveCount(3);

    // Setelah satu kartu direview, ia dijadwalkan ke masa depan.
    $srs->review($card, $user, SrsService::QUALITY_GOOD);

    expect($srs->dueCards($document, $user))->toHaveCount(2);
});
