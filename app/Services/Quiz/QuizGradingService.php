<?php

namespace App\Services\Quiz;

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mengelola siklus pengerjaan kuis: mulai attempt, nilai jawaban, hitung skor.
 *
 * Murni logika web (tanpa AI) — hemat kuota. Penilaian short_answer memakai
 * pencocokan teks sederhana (normalisasi + kecocokan substring) yang sengaja
 * ringan; tidak memanggil AI untuk menilai isian bebas.
 */
class QuizGradingService
{
    /** Mulai sesi pengerjaan kuis baru. */
    public function start(Quiz $quiz, User $user): QuizAttempt
    {
        return QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'status' => QuizAttempt::STATUS_IN_PROGRESS,
            'total_questions' => $quiz->question_count,
            'started_at' => now(),
        ]);
    }

    /**
     * Nilai jawaban user, simpan ke quiz_answers, hitung skor, dan tutup attempt.
     *
     * @param  array<int, array{option_id?:int|null, text?:string|null}>  $responses
     *         Dikunci berdasarkan quiz_question_id.
     *
     * @throws RuntimeException bila attempt sudah selesai.
     */
    public function submit(QuizAttempt $attempt, array $responses): QuizAttempt
    {
        if ($attempt->isCompleted()) {
            throw new RuntimeException('Sesi kuis ini sudah diselesaikan.');
        }

        $questions = $attempt->quiz->questions()->with('options')->get();

        DB::transaction(function () use ($attempt, $questions, $responses) {
            // Hapus jawaban lama bila ada (attempt in_progress yang disubmit ulang).
            $attempt->answers()->delete();

            $correctCount = 0;

            foreach ($questions as $question) {
                $response = $responses[$question->id] ?? [];
                $optionId = isset($response['option_id']) ? (int) $response['option_id'] : null;
                $text = $response['text'] ?? null;

                $isCorrect = $this->isResponseCorrect($question, $optionId, $text);
                if ($isCorrect) {
                    $correctCount++;
                }

                QuizAnswer::create([
                    'quiz_attempt_id' => $attempt->id,
                    'quiz_question_id' => $question->id,
                    'selected_option_id' => $question->isOptionBased() ? ($optionId ?: null) : null,
                    'answer_text' => $question->type === QuizQuestion::TYPE_SHORT_ANSWER
                        ? (is_string($text) ? trim($text) : null)
                        : null,
                    'is_correct' => $isCorrect,
                ]);
            }

            $total = $questions->count();
            $score = $total > 0 ? (int) round($correctCount / $total * 100) : 0;

            $attempt->update([
                'status' => QuizAttempt::STATUS_COMPLETED,
                'score' => $score,
                'correct_count' => $correctCount,
                'total_questions' => $total,
                'time_spent_seconds' => $this->elapsedSeconds($attempt),
                'completed_at' => now(),
            ]);

            $this->refreshQuizStats($attempt->quiz);
        });

        return $attempt->refresh();
    }

    /**
     * Saran tingkat kesulitan kuis lanjutan ("adaptive") berdasarkan skor:
     * skor tinggi → tantang dengan soal lebih sulit; skor rendah → turunkan.
     */
    public function suggestDifficulty(int $score): string
    {
        return match (true) {
            $score >= 80 => Quiz::DIFFICULTY_HARD,
            $score >= 50 => Quiz::DIFFICULTY_MEDIUM,
            default => Quiz::DIFFICULTY_EASY,
        };
    }

    /**
     * Tentukan apakah jawaban user benar.
     */
    protected function isResponseCorrect(QuizQuestion $question, ?int $optionId, ?string $text): bool
    {
        if ($question->isOptionBased()) {
            if (! $optionId) {
                return false;
            }

            $option = $question->options->firstWhere('id', $optionId);

            return $option !== null && $option->is_correct;
        }

        // short_answer — pencocokan teks sederhana.
        return $this->shortAnswerMatches((string) $text, (string) $question->correct_answer);
    }

    /**
     * Cocokkan jawaban singkat: sama persis setelah normalisasi, atau salah
     * satu memuat yang lain (toleransi jawaban sedikit lebih panjang/pendek).
     */
    protected function shortAnswerMatches(string $given, string $expected): bool
    {
        $g = $this->normalize($given);
        $e = $this->normalize($expected);

        if ($g === '' || $e === '') {
            return false;
        }

        if ($g === $e) {
            return true;
        }

        // Toleransi substring hanya bila jawaban acuan cukup panjang
        // (hindari kecocokan kebetulan pada kata sangat pendek).
        return mb_strlen($e) >= 4 && (str_contains($g, $e) || str_contains($e, $g));
    }

    protected function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[^\p{L}\p{N}\s]/u', '', $s) ?? $s;

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    /** Lama pengerjaan dalam detik (dari started_at ke sekarang). */
    protected function elapsedSeconds(QuizAttempt $attempt): ?int
    {
        if ($attempt->started_at === null) {
            return null;
        }

        return (int) abs($attempt->started_at->diffInSeconds(now()));
    }

    /** Hitung ulang total_attempts & average_score kuis dari attempt selesai. */
    protected function refreshQuizStats(Quiz $quiz): void
    {
        $completed = $quiz->attempts()
            ->where('status', QuizAttempt::STATUS_COMPLETED)
            ->get(['score']);

        $count = $completed->count();

        $quiz->update([
            'total_attempts' => $count,
            'average_score' => $count > 0 ? (int) round($completed->avg('score')) : null,
        ]);
    }
}
