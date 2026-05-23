<?php

namespace App\Services\Flashcard;

use App\Models\Document;
use App\Models\Flashcard;
use App\Models\FlashcardReview;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Spaced Repetition Service — implementasi algoritma SM-2 (SuperMemo 2).
 *
 * Tiap kali user menilai sebuah kartu, `review()` menghitung ease factor,
 * interval, dan repetisi baru lalu menjadwalkan `next_review_at`. Kartu yang
 * dinilai gagal (kualitas < 3) di-reset agar muncul kembali keesokan harinya.
 *
 * Pemetaan tombol penilaian UI → skala kualitas SM-2 (0-5):
 *  - "Sulit"  → 2  (kualitas < 3 = gagal → repetisi & interval di-reset)
 *  - "Cukup"  → 4  (lulus)
 *  - "Mudah"  → 5  (lulus dengan mudah → interval tumbuh paling cepat)
 */
class SrsService
{
    public const QUALITY_HARD = 2;

    public const QUALITY_GOOD = 4;

    public const QUALITY_EASY = 5;

    /**
     * Terapkan satu siklus SM-2 untuk kartu yang dinilai user, simpan state-nya.
     */
    public function review(Flashcard $flashcard, User $user, int $quality): FlashcardReview
    {
        $quality = max(0, min(5, $quality));

        $review = FlashcardReview::firstOrNew([
            'flashcard_id' => $flashcard->id,
            'user_id' => $user->id,
        ]);

        // State saat ini (pakai default SM-2 bila kartu belum pernah direview).
        $easeFactor = $review->exists
            ? (float) $review->ease_factor
            : FlashcardReview::DEFAULT_EASE_FACTOR;
        $interval = $review->exists ? (int) $review->interval_days : 0;
        $repetitions = $review->exists ? (int) $review->repetitions : 0;

        if ($quality >= 3) {
            // Jawaban benar — naikkan interval sesuai tahap repetisi.
            $interval = match ($repetitions) {
                0 => 1,
                1 => 6,
                default => (int) round($interval * $easeFactor),
            };
            $repetitions++;
        } else {
            // Jawaban gagal — ulangi kartu dari awal.
            $repetitions = 0;
            $interval = 1;
        }

        // Perbarui ease factor (SM-2 — dilakukan setiap review, termasuk gagal).
        $easeFactor += 0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02);
        $easeFactor = max(FlashcardReview::MIN_EASE_FACTOR, $easeFactor);

        $review->fill([
            'ease_factor' => round($easeFactor, 2),
            'interval_days' => $interval,
            'repetitions' => $repetitions,
            'quality' => $quality,
            'last_reviewed_at' => now(),
            'next_review_at' => now()->addDays($interval),
        ]);
        $review->save();

        return $review;
    }

    /**
     * Kartu dokumen yang jatuh tempo untuk seorang user: belum pernah direview,
     * atau `next_review_at` sudah lewat / sama dengan sekarang.
     *
     * @return Collection<int, Flashcard>
     */
    public function dueCards(Document $document, User $user): Collection
    {
        $cards = $document->flashcards()->orderBy('position')->get();

        if ($cards->isEmpty()) {
            return collect();
        }

        // Ambil semua review user untuk kartu-kartu ini sekaligus (hindari N+1).
        $reviews = FlashcardReview::where('user_id', $user->id)
            ->whereIn('flashcard_id', $cards->pluck('id'))
            ->get()
            ->keyBy('flashcard_id');

        return $cards->filter(function (Flashcard $card) use ($reviews) {
            $review = $reviews->get($card->id);

            return $review === null
                || $review->next_review_at === null
                || $review->next_review_at->lte(now());
        })->values();
    }

    /**
     * Waktu review paling awal di antara kartu dokumen yang sudah dijadwalkan
     * ke masa depan. Null bila tidak ada kartu yang menunggu jadwal.
     */
    public function nextReviewAt(Document $document, User $user): ?Carbon
    {
        $min = FlashcardReview::where('user_id', $user->id)
            ->whereIn('flashcard_id', $document->flashcards()->select('id'))
            ->where('next_review_at', '>', now())
            ->min('next_review_at');

        return $min !== null ? Carbon::parse($min) : null;
    }
}
