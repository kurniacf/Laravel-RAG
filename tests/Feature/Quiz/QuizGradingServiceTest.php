<?php

use App\Models\Document;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Quiz\QuizGradingService;

/**
 * Kuis siap-nilai: mcq + true_false + short_answer dengan jawaban benar diketahui.
 */
function makeGradableQuiz(): Quiz
{
    $owner = User::factory()->create();

    $doc = Document::create([
        'user_id' => $owner->id,
        'title' => 'Materi Nilai',
        'original_filename' => 'n.pdf',
        'file_path' => 'documents/'.$owner->id.'/n.pdf',
        'file_size_bytes' => 1024,
        'status' => Document::STATUS_READY,
        'total_chunks' => 3,
    ]);

    $quiz = Quiz::create([
        'document_id' => $doc->id,
        'user_id' => $owner->id,
        'title' => 'Kuis Uji',
        'difficulty' => Quiz::DIFFICULTY_MEDIUM,
        'question_count' => 3,
    ]);

    $mcq = QuizQuestion::create([
        'quiz_id' => $quiz->id,
        'type' => QuizQuestion::TYPE_MCQ,
        'question_text' => 'Manakah yang benar?',
        'correct_answer' => 'Opsi B',
        'explanation' => 'Opsi B benar.',
        'difficulty' => Quiz::DIFFICULTY_MEDIUM,
        'position' => 0,
    ]);
    foreach (['Opsi A' => false, 'Opsi B' => true, 'Opsi C' => false, 'Opsi D' => false] as $text => $correct) {
        QuizOption::create(['quiz_question_id' => $mcq->id, 'option_text' => $text, 'is_correct' => $correct]);
    }

    $tf = QuizQuestion::create([
        'quiz_id' => $quiz->id,
        'type' => QuizQuestion::TYPE_TRUE_FALSE,
        'question_text' => 'Pernyataan ini benar.',
        'correct_answer' => 'Benar',
        'explanation' => 'Memang benar.',
        'difficulty' => Quiz::DIFFICULTY_MEDIUM,
        'position' => 1,
    ]);
    QuizOption::create(['quiz_question_id' => $tf->id, 'option_text' => 'Benar', 'is_correct' => true]);
    QuizOption::create(['quiz_question_id' => $tf->id, 'option_text' => 'Salah', 'is_correct' => false]);

    QuizQuestion::create([
        'quiz_id' => $quiz->id,
        'type' => QuizQuestion::TYPE_SHORT_ANSWER,
        'question_text' => 'Sebutkan proses tumbuhan membuat makanan.',
        'correct_answer' => 'Fotosintesis',
        'explanation' => 'Itu fotosintesis.',
        'difficulty' => Quiz::DIFFICULTY_MEDIUM,
        'position' => 2,
    ]);

    return $quiz;
}

/** Bangun respons "semua benar" untuk kuis. */
function allCorrectResponses(Quiz $quiz): array
{
    $responses = [];
    foreach ($quiz->questions()->with('options')->get() as $q) {
        $responses[$q->id] = $q->isOptionBased()
            ? ['option_id' => $q->options->firstWhere('is_correct', true)->id]
            : ['text' => 'fotosintesis']; // huruf kecil — uji normalisasi
    }

    return $responses;
}

test('start membuat attempt berstatus in_progress', function () {
    $quiz = makeGradableQuiz();
    $taker = User::factory()->create();

    $attempt = app(QuizGradingService::class)->start($quiz, $taker);

    expect($attempt->status)->toBe(QuizAttempt::STATUS_IN_PROGRESS);
    expect($attempt->quiz_id)->toBe($quiz->id);
    expect($attempt->user_id)->toBe($taker->id);
    expect($attempt->started_at)->not->toBeNull();
});

test('submit semua jawaban benar menghasilkan skor 100', function () {
    $quiz = makeGradableQuiz();
    $taker = User::factory()->create();
    $service = app(QuizGradingService::class);

    $attempt = $service->start($quiz, $taker);
    $attempt = $service->submit($attempt, allCorrectResponses($quiz));

    expect($attempt->score)->toBe(100);
    expect($attempt->correct_count)->toBe(3);
    expect($attempt->status)->toBe(QuizAttempt::STATUS_COMPLETED);
    expect($attempt->answers()->count())->toBe(3);
});

test('submit jawaban sebagian benar menghitung skor persen yang tepat', function () {
    $quiz = makeGradableQuiz();
    $taker = User::factory()->create();
    $service = app(QuizGradingService::class);
    $questions = $quiz->questions()->with('options')->get();

    $mcq = $questions->firstWhere('type', QuizQuestion::TYPE_MCQ);
    $tf = $questions->firstWhere('type', QuizQuestion::TYPE_TRUE_FALSE);
    $sa = $questions->firstWhere('type', QuizQuestion::TYPE_SHORT_ANSWER);

    // Hanya mcq yang benar; tf & short_answer salah → 1 dari 3.
    $responses = [
        $mcq->id => ['option_id' => $mcq->options->firstWhere('is_correct', true)->id],
        $tf->id => ['option_id' => $tf->options->firstWhere('is_correct', false)->id],
        $sa->id => ['text' => 'jawaban keliru'],
    ];

    $attempt = $service->start($quiz, $taker);
    $attempt = $service->submit($attempt, $responses);

    expect($attempt->correct_count)->toBe(1);
    expect($attempt->score)->toBe(33); // round(1/3 * 100)
});

test('submit memperbarui total_attempts dan average_score quiz', function () {
    $quiz = makeGradableQuiz();
    $service = app(QuizGradingService::class);

    // Attempt 1: skor 100.
    $a1 = $service->start($quiz, User::factory()->create());
    $service->submit($a1, allCorrectResponses($quiz));

    // Attempt 2: semua salah → skor 0.
    $a2 = $service->start($quiz, User::factory()->create());
    $service->submit($a2, []);

    $quiz->refresh();
    expect($quiz->total_attempts)->toBe(2);
    expect($quiz->average_score)->toBe(50); // (100 + 0) / 2
});

test('submit pada attempt yang sudah selesai ditolak', function () {
    $quiz = makeGradableQuiz();
    $taker = User::factory()->create();
    $service = app(QuizGradingService::class);

    $attempt = $service->start($quiz, $taker);
    $service->submit($attempt, allCorrectResponses($quiz));

    expect(fn () => $service->submit($attempt->fresh(), allCorrectResponses($quiz)))
        ->toThrow(RuntimeException::class);
});

test('suggestDifficulty menyesuaikan tingkat dengan skor', function () {
    $service = app(QuizGradingService::class);

    expect($service->suggestDifficulty(90))->toBe(Quiz::DIFFICULTY_HARD);
    expect($service->suggestDifficulty(65))->toBe(Quiz::DIFFICULTY_MEDIUM);
    expect($service->suggestDifficulty(20))->toBe(Quiz::DIFFICULTY_EASY);
});
