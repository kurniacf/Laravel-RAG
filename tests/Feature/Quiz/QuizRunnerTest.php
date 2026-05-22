<?php

use App\Livewire\DocumentShow;
use App\Livewire\QuizRunner;
use App\Models\QuizAttempt;
use App\Models\User;
use Livewire\Livewire;

// Catatan: helper makeGradableQuiz() didefinisikan di QuizGradingServiceTest.php
// (fungsi global Pest) dan dipakai ulang di sini.

test('halaman kuis menolak tamu', function () {
    $quiz = makeGradableQuiz();

    $this->get(route('quizzes.show', $quiz))->assertRedirect('/login');
});

test('pemilik dokumen dapat membuka kuis', function () {
    $quiz = makeGradableQuiz();
    $owner = User::find($quiz->document->user_id);

    $this->actingAs($owner)
        ->get(route('quizzes.show', $quiz))
        ->assertOk()
        ->assertSee($quiz->title);
});

test('user lain tidak boleh membuka kuis dokumen orang lain', function () {
    $quiz = makeGradableQuiz();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->get(route('quizzes.show', $quiz))
        ->assertForbidden();
});

test('alur kerjakan kuis: mulai lalu submit menghasilkan attempt completed', function () {
    $quiz = makeGradableQuiz();
    $owner = User::find($quiz->document->user_id);
    $this->actingAs($owner);

    $component = Livewire::test(QuizRunner::class, ['quiz' => $quiz])
        ->assertSet('mode', 'overview')
        ->call('startQuiz')
        ->assertSet('mode', 'taking');

    // Jawab semua soal dengan benar.
    foreach ($quiz->questions()->with('options')->get() as $q) {
        $value = $q->isOptionBased()
            ? $q->options->firstWhere('is_correct', true)->id
            : 'fotosintesis';
        $component->set('answers.'.$q->id, $value);
    }

    $component->call('submitQuiz')->assertSet('mode', 'result');

    $attempt = QuizAttempt::where('quiz_id', $quiz->id)->first();
    expect($attempt)->not->toBeNull();
    expect($attempt->status)->toBe(QuizAttempt::STATUS_COMPLETED);
    expect($attempt->score)->toBe(100);
    expect($attempt->user_id)->toBe($owner->id);
});

test('halaman detail dokumen menampilkan daftar kuis', function () {
    $quiz = makeGradableQuiz();
    $owner = User::find($quiz->document->user_id);
    $this->actingAs($owner);

    Livewire::test(DocumentShow::class, ['document' => $quiz->document])
        ->assertOk()
        ->assertSee('Buat Kuis')
        ->assertSee($quiz->difficultyLabel());
});
