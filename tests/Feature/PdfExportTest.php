<?php

use App\Models\Document;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Support\Facades\View;

/**
 * Test export PDF — ringkasan & kuis.
 *
 * Catatan: render dompdf sebenarnya berat dan butuh font. Test ini fokus pada
 * kontrak HTTP (status code, header content-type, hak akses) — bukan
 * memvalidasi byte-by-byte PDF. Untuk mempercepat dan menghindari tergantung
 * font fixture, kita cukup memastikan endpoint mengembalikan response PDF
 * yang valid (content-type application/pdf, content-disposition attachment).
 */

function makePdfTestDocument(User $user, string $title = 'Dokumen PDF'): Document
{
    return Document::create([
        'user_id' => $user->id,
        'title' => $title,
        'original_filename' => 'd.pdf',
        'file_path' => 'documents/'.$user->id.'/d.pdf',
        'file_size_bytes' => 1024,
        'status' => Document::STATUS_READY,
        'total_chunks' => 0,
    ]);
}

function attachSummariesToDocument(Document $document): void
{
    Summary::create([
        'document_id' => $document->id,
        'type' => Summary::TYPE_EXECUTIVE,
        'content' => 'Ringkasan eksekutif dokumen ini menyoroti tiga gagasan utama: pertama, fotosintesis adalah proses fundamental; kedua, energi cahaya dikonversi menjadi energi kimia; ketiga, oksigen merupakan produk samping. Konteks Indonesia "déjà vu" — karakter khusus diuji.',
        'word_count' => 35,
        'model_used' => 'gemini-2.5-flash-lite',
    ]);

    Summary::create([
        'document_id' => $document->id,
        'type' => Summary::TYPE_PER_CHAPTER,
        'content' => "**Bab 1: Pendahuluan**\nMenjelaskan motivasi dan ruang lingkup.\n\n**Bab 2: Metode**\nMembahas langkah penelitian.",
        'word_count' => 12,
        'model_used' => 'gemini-2.5-flash-lite',
    ]);

    Summary::create([
        'document_id' => $document->id,
        'type' => Summary::TYPE_KEY_POINTS,
        'content' => "- Poin pertama yang penting\n- Poin kedua tentang implementasi\n- Poin ketiga menutup pembahasan",
        'word_count' => 14,
        'model_used' => 'gemini-2.5-flash-lite',
    ]);
}

test('endpoint pdf ringkasan menolak tamu', function () {
    $owner = User::factory()->create();
    $doc = makePdfTestDocument($owner);
    attachSummariesToDocument($doc);

    $this->get(route('documents.summary.pdf', $doc))->assertRedirect('/login');
});

test('user lain tidak boleh mengunduh ringkasan dokumen orang lain', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $doc = makePdfTestDocument($owner);
    attachSummariesToDocument($doc);

    $this->actingAs($intruder)
        ->get(route('documents.summary.pdf', $doc))
        ->assertForbidden();
});

test('admin dapat mengunduh ringkasan dokumen pengguna lain', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $doc = makePdfTestDocument($owner);
    attachSummariesToDocument($doc);

    $response = $this->actingAs($admin)
        ->get(route('documents.summary.pdf', $doc));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('export ringkasan menghasilkan PDF dengan nama file deskriptif', function () {
    $owner = User::factory()->create();
    $doc = makePdfTestDocument($owner, 'Pengantar Kalkulus Bab 1');
    attachSummariesToDocument($doc);

    $response = $this->actingAs($owner)
        ->get(route('documents.summary.pdf', $doc));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('ringkasan-pengantar-kalkulus-bab-1.pdf');
});

test('export ringkasan gagal bila dokumen belum punya ringkasan', function () {
    $owner = User::factory()->create();
    $doc = makePdfTestDocument($owner);
    // Tidak ada ringkasan dipasang.

    $this->actingAs($owner)
        ->get(route('documents.summary.pdf', $doc))
        ->assertNotFound();
});

test('blade ringkasan dapat dirender (smoke test, tanpa dompdf)', function () {
    $owner = User::factory()->create();
    $doc = makePdfTestDocument($owner, 'Materi Smoke');
    attachSummariesToDocument($doc);

    $html = View::make('pdf.summary', [
        'document' => $doc->load('subject', 'user'),
        'summaries' => $doc->summaries()->get()->keyBy('type'),
        'generatedAt' => now(),
    ])->render();

    expect($html)
        ->toContain('Materi Smoke')
        ->toContain('Ringkasan Eksekutif')
        ->toContain('Ringkasan Per Bagian')
        ->toContain('Poin Kunci')
        ->toContain('déjà vu') // karakter khusus Bahasa Indonesia/Eropa
        ->toContain('Pendahuluan') // heading dari section
        ->toContain('Poin pertama yang penting');
});

test('endpoint pdf kuis menolak tamu', function () {
    $quiz = makeGradableQuiz();

    $this->get(route('quizzes.export.pdf', $quiz))->assertRedirect('/login');
});

test('export kuis lengkap berisi kunci jawaban', function () {
    $quiz = makeGradableQuiz();
    $owner = User::find($quiz->document->user_id);

    $response = $this->actingAs($owner)
        ->get(route('quizzes.export.pdf', ['quiz' => $quiz, 'key' => 1]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('kunci-jawaban');
});

test('export kuis lembar soal pakai suffix berbeda', function () {
    $quiz = makeGradableQuiz();
    $owner = User::find($quiz->document->user_id);

    $response = $this->actingAs($owner)
        ->get(route('quizzes.export.pdf', ['quiz' => $quiz, 'key' => 0]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('lembar-soal');
});

test('blade kuis dapat dirender dengan dan tanpa kunci jawaban', function () {
    $quiz = makeGradableQuiz();
    $quiz->loadMissing('document.subject', 'document.user', 'questions.options');

    $htmlWithKey = View::make('pdf.quiz', [
        'quiz' => $quiz,
        'withAnswerKey' => true,
        'generatedAt' => now(),
    ])->render();

    expect($htmlWithKey)
        ->toContain('Kuis Uji')
        ->toContain('Kunci Jawaban')
        ->toContain('Opsi B')
        ->toContain('Pembahasan');

    $htmlNoKey = View::make('pdf.quiz', [
        'quiz' => $quiz,
        'withAnswerKey' => false,
        'generatedAt' => now(),
    ])->render();

    expect($htmlNoKey)
        ->toContain('Kuis Uji')
        ->not->toContain('Kunci Jawaban');
});
