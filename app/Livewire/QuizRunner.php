<?php

namespace App\Livewire;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\Quiz\QuizGeneratorService;
use App\Services\Quiz\QuizGradingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * Halaman pengerjaan satu kuis. Satu komponen menampung tiga mode:
 *  overview — ikhtisar kuis + riwayat attempt + tombol mulai.
 *  taking   — semua soal tampil sekaligus dalam satu form.
 *  result   — skor + review per soal + opsi ulangi / kuis lanjutan adaptif.
 *
 * Soal ditampilkan sekaligus (bukan satu per satu): untuk 3-15 soal ini lebih
 * sederhana secara state, satu kali submit, dan review-nya gampang dibaca.
 */
#[Layout('layouts.app')]
#[Title('Kuis')]
class QuizRunner extends Component
{
    public Quiz $quiz;

    /** Mode tampilan: overview | taking | result. */
    public string $mode = 'overview';

    /** Attempt yang sedang dikerjakan / ditinjau. */
    public ?int $attemptId = null;

    /** Jawaban user, dikunci berdasarkan id soal (option id atau teks). */
    public array $answers = [];

    public function mount(Quiz $quiz): void
    {
        $this->authorizeAccess($quiz);
        $this->quiz = $quiz;
    }

    /** @return Collection<int, \App\Models\QuizQuestion> */
    #[Computed]
    public function questions(): Collection
    {
        return $this->quiz->questions()->with('options')->get();
    }

    /** Riwayat attempt milik user untuk kuis ini. */
    #[Computed]
    public function pastAttempts(): Collection
    {
        return $this->quiz->attempts()
            ->where('user_id', auth()->id())
            ->where('status', QuizAttempt::STATUS_COMPLETED)
            ->latest('completed_at')
            ->get();
    }

    /** Attempt yang sedang aktif / ditinjau. */
    #[Computed]
    public function attempt(): ?QuizAttempt
    {
        return $this->attemptId
            ? QuizAttempt::with('answers')->find($this->attemptId)
            : null;
    }

    /** Mulai sesi pengerjaan kuis. */
    public function startQuiz(QuizGradingService $grader): void
    {
        $this->authorizeAccess($this->quiz);

        if ($this->quiz->question_count < 1) {
            session()->flash('error', 'Kuis ini belum memiliki soal.');

            return;
        }

        $attempt = $grader->start($this->quiz, auth()->user());
        $this->attemptId = $attempt->id;
        $this->answers = [];
        $this->mode = 'taking';
    }

    /** Kumpulkan & nilai jawaban. */
    public function submitQuiz(QuizGradingService $grader): void
    {
        $attempt = $this->attempt;

        if (! $attempt || $attempt->user_id !== auth()->id() || $attempt->isCompleted()) {
            session()->flash('error', 'Sesi kuis tidak valid.');
            $this->mode = 'overview';

            return;
        }

        $responses = [];
        foreach ($this->questions as $q) {
            $value = $this->answers[$q->id] ?? null;
            $responses[$q->id] = $q->isOptionBased()
                ? ['option_id' => ($value !== null && $value !== '') ? (int) $value : null]
                : ['text' => is_string($value) ? $value : null];
        }

        try {
            $grader->submit($attempt, $responses);
            unset($this->attempt, $this->pastAttempts);
            $this->mode = 'result';
        } catch (Throwable $e) {
            session()->flash('error', 'Gagal menilai kuis: '.$e->getMessage());
        }
    }

    /** Ulangi kuis yang sama dari awal. */
    public function retakeQuiz(QuizGradingService $grader): void
    {
        $this->startQuiz($grader);
    }

    /**
     * "Adaptive": buat kuis lanjutan dari dokumen yang sama dengan tingkat
     * kesulitan disesuaikan performa attempt terakhir.
     */
    public function generateFollowUp(QuizGeneratorService $generator, QuizGradingService $grader): void
    {
        $attempt = $this->attempt;
        if (! $attempt) {
            return;
        }

        $difficulty = $grader->suggestDifficulty((int) $attempt->score);

        try {
            $quiz = $generator->generate(
                $this->quiz->document,
                max(QuizGeneratorService::MIN_QUESTIONS, $this->quiz->question_count),
                $difficulty,
            );
            session()->flash('status', 'Kuis lanjutan tingkat '.Quiz::difficultyLabelFor($difficulty).' siap dikerjakan.');
            $this->redirectRoute('quizzes.show', $quiz, navigate: true);
        } catch (Throwable $e) {
            session()->flash('error', 'Gagal membuat kuis lanjutan: '.$e->getMessage());
        }
    }

    /** User biasa hanya boleh mengakses kuis dari dokumen miliknya. */
    protected function authorizeAccess(Quiz $quiz): void
    {
        $user = auth()->user();

        abort_if(! $user->isAdmin() && $quiz->document->user_id !== $user->id, 403);
    }

    public function render(): View
    {
        return view('livewire.quiz-runner');
    }
}
