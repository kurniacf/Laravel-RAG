<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Quiz;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint export PDF — dua jenis konten:
 *   1. Ringkasan tiga tingkat (executive + per_chapter + key_points)
 *   2. Lembar kuis (mode: lengkap dengan kunci, atau lembar soal saja)
 *
 * Tidak memanggil AI sama sekali, hanya merangkai data DB ke template Blade
 * lalu dirender dompdf. Hak akses meniru DocumentShow/QuizRunner: admin
 * bebas; user biasa hanya boleh mengakses miliknya.
 */
class PdfExportController extends Controller
{
    /**
     * Unduh PDF ringkasan dokumen — gabungan eksekutif, per bagian, poin kunci.
     */
    public function summary(Document $document): Response
    {
        $this->authorizeAccess($document);

        $summaries = $document->summaries()->get()->keyBy('type');

        abort_if($summaries->isEmpty(), 404, 'Belum ada ringkasan untuk dokumen ini.');

        $pdf = Pdf::loadView('pdf.summary', [
            'document' => $document->loadMissing('subject', 'user'),
            'summaries' => $summaries,
            'generatedAt' => now(),
        ])->setPaper('a4');

        $filename = 'ringkasan-'.Str::slug($document->title).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Unduh PDF kuis. Query string `key`:
     *   - key=1 (default) → tampilkan kunci jawaban + pembahasan (untuk belajar).
     *   - key=0           → lembar soal kosong (untuk dicetak & dikerjakan ulang).
     */
    public function quiz(Quiz $quiz, Request $request): Response
    {
        $this->authorizeAccess($quiz->document);

        $quiz->loadMissing('document.subject', 'document.user', 'questions.options');

        $withAnswerKey = $request->boolean('key', true);

        $pdf = Pdf::loadView('pdf.quiz', [
            'quiz' => $quiz,
            'withAnswerKey' => $withAnswerKey,
            'generatedAt' => now(),
        ])->setPaper('a4');

        $suffix = $withAnswerKey ? 'kunci-jawaban' : 'lembar-soal';
        $filename = 'kuis-'.Str::slug($quiz->title).'-'.$suffix.'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Pemeriksaan kepemilikan: user biasa hanya boleh akses dokumen sendiri.
     */
    protected function authorizeAccess(Document $document): void
    {
        $user = auth()->user();

        abort_if(! $user || (! $user->isAdmin() && $document->user_id !== $user->id), 403);
    }
}
