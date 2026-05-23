<?php

use App\Livewire\FlashcardStudy;
use App\Models\Document;
use App\Models\User;
use Livewire\Livewire;

// Helper makeSrsFlashcard() didefinisikan di SrsServiceTest.php (fungsi global Pest).

test('halaman belajar flashcard menolak tamu', function () {
    $card = makeSrsFlashcard();

    $this->get(route('flashcards.study', $card->document))->assertRedirect('/login');
});

test('pemilik dapat membuka halaman belajar flashcard', function () {
    $card = makeSrsFlashcard();

    $this->actingAs($card->user)
        ->get(route('flashcards.study', $card->document))
        ->assertOk();
});

test('user lain tidak boleh membuka belajar flashcard dokumen orang lain', function () {
    $card = makeSrsFlashcard();

    $this->actingAs(User::factory()->create())
        ->get(route('flashcards.study', $card->document))
        ->assertForbidden();
});

test('mode empty bila dokumen belum punya flashcard', function () {
    $owner = User::factory()->create();
    $doc = Document::create([
        'user_id' => $owner->id,
        'title' => 'Tanpa Kartu',
        'original_filename' => 't.pdf',
        'file_path' => 'documents/'.$owner->id.'/t.pdf',
        'file_size_bytes' => 100,
        'status' => Document::STATUS_READY,
    ]);

    $this->actingAs($owner);

    Livewire::test(FlashcardStudy::class, ['document' => $doc])
        ->assertSet('mode', 'empty');
});

test('alur belajar: flip lalu nilai kartu memajukan sesi hingga selesai', function () {
    $card1 = makeSrsFlashcard();
    $doc = $card1->document;
    makeSrsFlashcard($doc, 1);

    $this->actingAs($card1->user);

    $component = Livewire::test(FlashcardStudy::class, ['document' => $doc])
        ->assertSet('mode', 'study')
        ->assertSet('flipped', false)
        ->call('flip')
        ->assertSet('flipped', true)
        ->call('rate', 'good')
        ->assertSet('position', 1)
        ->assertSet('flipped', false);

    // Nilai kartu kedua → sesi selesai.
    $component->call('flip')
        ->call('rate', 'easy')
        ->assertSet('mode', 'done');

    expect($component->get('reviewedCount'))->toBe(2);
});

test('rate diabaikan bila kartu belum di-flip', function () {
    $card = makeSrsFlashcard();

    $this->actingAs($card->user);

    Livewire::test(FlashcardStudy::class, ['document' => $card->document])
        ->call('rate', 'good')
        ->assertSet('position', 0)   // tidak maju
        ->assertSet('reviewedCount', 0);
});
