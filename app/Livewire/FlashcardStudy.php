<?php

namespace App\Livewire;

use App\Models\Document;
use App\Models\Flashcard;
use App\Services\Flashcard\SrsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Halaman belajar flashcard untuk satu dokumen.
 *
 * Menampilkan kartu yang jatuh tempo (Spaced Repetition) satu per satu:
 * lihat sisi depan → flip → nilai penguasaan diri. Penilaian memanggil
 * SrsService (SM-2) untuk menjadwalkan ulang kartu. Tanpa panggilan AI.
 */
#[Layout('layouts.app')]
#[Title('Belajar Flashcard')]
class FlashcardStudy extends Component
{
    public Document $document;

    /** Id kartu untuk sesi belajar ini, berurutan. */
    public array $queue = [];

    public int $position = 0;

    public bool $flipped = false;

    /** Mode tampilan: study | done | empty. */
    public string $mode = 'study';

    public int $reviewedCount = 0;

    public int $masteredCount = 0;

    public function mount(Document $document, SrsService $srs): void
    {
        $this->authorizeAccess($document);
        $this->document = $document;
        $this->loadQueue($srs);
    }

    /**
     * Susun antrean kartu sesi. Default: hanya kartu jatuh tempo. Bila
     * $all true, ambil semua kartu (mode latihan ulang).
     */
    protected function loadQueue(SrsService $srs, bool $all = false): void
    {
        $cards = $all
            ? $this->document->flashcards()->orderBy('position')->get()
            : $srs->dueCards($this->document, auth()->user());

        $this->queue = $cards->pluck('id')->all();
        $this->position = 0;
        $this->flipped = false;
        $this->reviewedCount = 0;
        $this->masteredCount = 0;
        $this->mode = empty($this->queue) ? 'empty' : 'study';
    }

    #[Computed]
    public function totalCards(): int
    {
        return $this->document->flashcards()->count();
    }

    #[Computed]
    public function currentCard(): ?Flashcard
    {
        $id = $this->queue[$this->position] ?? null;

        return $id !== null ? Flashcard::find($id) : null;
    }

    /** Waktu review berikutnya (untuk ditampilkan di layar selesai/kosong). */
    public function nextReviewAt(): ?Carbon
    {
        return app(SrsService::class)->nextReviewAt($this->document, auth()->user());
    }

    /** Balik kartu untuk melihat sisi belakang. */
    public function flip(): void
    {
        $this->flipped = true;
    }

    /**
     * Nilai kartu saat ini, jadwalkan ulang via SM-2, lalu maju ke kartu
     * berikutnya. $rating: hard | good | easy.
     */
    public function rate(string $rating, SrsService $srs): void
    {
        if ($this->mode !== 'study' || ! $this->flipped) {
            return;
        }

        $quality = match ($rating) {
            'hard' => SrsService::QUALITY_HARD,
            'good' => SrsService::QUALITY_GOOD,
            'easy' => SrsService::QUALITY_EASY,
            default => null,
        };

        $card = $this->currentCard;

        if ($quality === null || $card === null) {
            return;
        }

        $review = $srs->review($card, auth()->user(), $quality);
        $this->reviewedCount++;
        if ($review->isMastered()) {
            $this->masteredCount++;
        }

        $this->position++;
        $this->flipped = false;
        unset($this->currentCard);

        if ($this->position >= count($this->queue)) {
            $this->mode = 'done';
        }
    }

    /** Pelajari ulang SEMUA kartu dokumen (latihan, mengabaikan jadwal). */
    public function studyAll(SrsService $srs): void
    {
        $this->loadQueue($srs, all: true);
    }

    protected function authorizeAccess(Document $document): void
    {
        $user = auth()->user();

        abort_if(! $user->isAdmin() && $document->user_id !== $user->id, 403);
    }

    public function render(): View
    {
        return view('livewire.flashcard-study');
    }
}
