<?php

namespace App\Livewire;

use App\Models\Document;
use App\Models\Quiz;
use App\Models\Summary;
use App\Services\Quiz\QuizGeneratorService;
use App\Services\Rag\DocumentIndexer;
use App\Services\Summary\SummaryService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * Halaman detail satu dokumen. Menjadi rumah aksi-aksi AI tingkat dokumen:
 * Proses ke Vector, Chat, dan (Tier 2) Auto-Summary serta Kuis.
 */
#[Layout('layouts.app')]
#[Title('Detail Dokumen')]
class DocumentShow extends Component
{
    public Document $document;

    /** Form pembuatan kuis. */
    public int $quizQuestionCount = 5;

    public string $quizDifficulty = Quiz::DIFFICULTY_MEDIUM;

    public function mount(Document $document): void
    {
        $this->authorizeAccess($document);
        $this->document = $document;
    }

    /**
     * Ringkasan dokumen di-keying berdasarkan tipe agar mudah diakses per
     * tab di view. Dibaca dari DB saja — TIDAK memanggil AI saat halaman load.
     *
     * @return Collection<string, Summary>
     */
    #[Computed]
    public function summaries(): Collection
    {
        return $this->document->summaries()->get()->keyBy('type');
    }

    /**
     * Daftar kuis milik dokumen ini, terbaru lebih dulu.
     *
     * @return Collection<int, Quiz>
     */
    #[Computed]
    public function quizzes(): Collection
    {
        return $this->document->quizzes()->latest()->get();
    }

    /**
     * Trigger eksplisit: buat (atau buat ulang) ketiga ringkasan via Gemini.
     * Hemat kuota — hanya jalan saat tombol diklik, hasilnya di-cache di DB.
     */
    public function generateSummary(SummaryService $service): void
    {
        $this->authorizeAccess($this->document);

        if (! $this->document->isReady()) {
            session()->flash('error', 'Hanya dokumen berstatus "Siap" yang dapat diringkas.');

            return;
        }

        try {
            $service->generateAll($this->document);
            // Bust cache computed agar tab ringkasan menampilkan hasil terbaru.
            unset($this->summaries);
            session()->flash('status', 'Ringkasan tiga tingkat berhasil dibuat.');
        } catch (Throwable $e) {
            session()->flash('error', 'Gagal membuat ringkasan: '.$e->getMessage());
        }
    }

    /**
     * Trigger eksplisit: buat kuis baru dari dokumen, lalu arahkan ke halaman
     * pengerjaan kuis. Soal disusun dari chunk dokumen.
     */
    public function generateQuiz(QuizGeneratorService $service): void
    {
        $this->authorizeAccess($this->document);

        if (($this->document->total_chunks ?? 0) < 1) {
            session()->flash('error', 'Proses dokumen ke vector terlebih dahulu sebelum membuat kuis.');

            return;
        }

        $this->validate([
            'quizQuestionCount' => ['required', 'integer', 'min:3', 'max:15'],
            'quizDifficulty' => ['required', Rule::in(Quiz::DIFFICULTIES)],
        ]);

        try {
            $quiz = $service->generate($this->document, $this->quizQuestionCount, $this->quizDifficulty);
            session()->flash('status', 'Kuis berhasil dibuat. Selamat mengerjakan!');
            $this->redirectRoute('quizzes.show', $quiz, navigate: true);
        } catch (Throwable $e) {
            session()->flash('error', 'Gagal membuat kuis: '.$e->getMessage());
        }
    }

    /**
     * Hapus satu kuis milik dokumen ini (beserta soal & riwayat — cascade).
     */
    public function deleteQuiz(int $quizId): void
    {
        $this->authorizeAccess($this->document);

        $quiz = $this->document->quizzes()->whereKey($quizId)->first();

        if ($quiz !== null) {
            $quiz->delete();
            unset($this->quizzes);
            session()->flash('status', 'Kuis telah dihapus.');
        }
    }

    /**
     * Trigger indexing ke vector store (chunk + embed) langsung dari halaman ini.
     */
    public function indexToVector(DocumentIndexer $indexer): void
    {
        $this->authorizeAccess($this->document);

        if (! $this->document->isReady()) {
            session()->flash('error', 'Hanya dokumen dengan status "Siap" yang dapat diproses ke vector.');

            return;
        }

        try {
            $indexer->index($this->document);
            $this->document->refresh();
            session()->flash('status', "Dokumen diproses menjadi {$this->document->total_chunks} chunk vector.");
        } catch (Throwable $e) {
            session()->flash('error', 'Gagal memproses ke vector: '.$e->getMessage());
        }
    }

    /**
     * User biasa hanya boleh mengakses dokumen miliknya; admin boleh semua.
     */
    protected function authorizeAccess(Document $document): void
    {
        $user = auth()->user();

        abort_if(! $user->isAdmin() && $document->user_id !== $user->id, 403);
    }

    public function render(): View
    {
        return view('livewire.document-show');
    }
}
