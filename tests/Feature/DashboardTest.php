<?php

use App\Models\Document;
use App\Models\Flashcard;
use App\Models\FlashcardReview;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Livewire\Volt\Volt;

/** Buat satu dokumen ready milik user. */
function makeDashboardDocument(User $user, string $title = 'Dokumen'): Document
{
    return Document::create([
        'user_id' => $user->id,
        'title' => $title,
        'original_filename' => 'd.pdf',
        'file_path' => 'documents/'.$user->id.'/'.uniqid().'.pdf',
        'file_size_bytes' => 100,
        'status' => Document::STATUS_READY,
    ]);
}

test('dashboard menolak tamu', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('dashboard user baru tanpa dokumen menampilkan empty state', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Mulai perjalanan belajarmu')
        ->assertDontSee('Statistik Belajar');
});

test('dashboard user dengan dokumen menampilkan statistik dan analitik', function () {
    $user = User::factory()->create();
    makeDashboardDocument($user);

    $this->actingAs($user);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Statistik Belajar')
        ->assertSee('Dokumen Saya')
        ->assertSee('Flashcard Dikuasai')
        ->assertSee('Aktivitas Terbaru');
});

test('dashboard admin menampilkan label Total Dokumen dan statistik sistem', function () {
    $admin = User::factory()->admin()->create();
    makeDashboardDocument($admin);

    $this->actingAs($admin);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Total Dokumen')
        ->assertSee('Total Pengguna Terdaftar');
});

test('dashboard mengagregasi statistik belajar user dengan benar', function () {
    $user = User::factory()->create();

    $doc = makeDashboardDocument($user, 'Materi A');
    makeDashboardDocument($user, 'Materi B');

    // Kuis + dua attempt selesai dengan skor 80 & 60 → rata-rata 70.
    $quiz = Quiz::create([
        'document_id' => $doc->id,
        'user_id' => $user->id,
        'title' => 'Kuis Uji',
        'difficulty' => Quiz::DIFFICULTY_MEDIUM,
        'question_count' => 5,
    ]);
    foreach ([80, 60] as $score) {
        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'status' => QuizAttempt::STATUS_COMPLETED,
            'score' => $score,
            'correct_count' => 4,
            'total_questions' => 5,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    // Dua flashcard: satu sudah dikuasai (repetisi 3), satu belum (repetisi 1).
    foreach ([3, 1] as $i => $repetitions) {
        $card = Flashcard::create([
            'document_id' => $doc->id,
            'user_id' => $user->id,
            'front_text' => 'Depan '.$i,
            'back_text' => 'Belakang '.$i,
            'difficulty' => 'medium',
            'position' => $i,
        ]);
        FlashcardReview::create([
            'flashcard_id' => $card->id,
            'user_id' => $user->id,
            'ease_factor' => 2.5,
            'interval_days' => $repetitions > 1 ? 15 : 1,
            'repetitions' => $repetitions,
            'next_review_at' => now()->addDays(3),
        ]);
    }

    $this->actingAs($user);

    Volt::test('pages.dashboard')
        ->assertSet('kosong', false)
        ->assertSet('jumlahDokumen', 2)
        ->assertSet('jumlahKuisDikerjakan', 2)
        ->assertSet('rataRataSkor', 70)
        ->assertSet('flashcardDikuasai', 1);
});
