<?php

use App\Http\Controllers\PdfExportController;
use App\Livewire\ActivityHistory;
use App\Livewire\Admin\UserManager;
use App\Livewire\Chat\ChatIndex;
use App\Livewire\Chat\ChatRoom;
use App\Livewire\DocumentManager;
use App\Livewire\DocumentShow;
use App\Livewire\FlashcardStudy;
use App\Livewire\QuizRunner;
use App\Livewire\SubjectManager;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Beranda: kalau sudah login langsung ke dashboard, kalau guest tampil landing.
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('landing');
})->name('landing');

Volt::route('dashboard', 'pages.dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Mata Pelajaran — semua user boleh melihat, hanya admin boleh kelola
// (cek role di dalam komponen SubjectManager).
Route::get('subjects', SubjectManager::class)
    ->middleware(['auth', 'verified'])
    ->name('subjects.index');

// Dokumen — user lihat miliknya, admin lihat semua (cek di komponen).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('documents', DocumentManager::class)->name('documents.index');
    // Halaman detail dokumen: aksi AI per dokumen (vektor, ringkasan, kuis).
    Route::get('documents/{document}', DocumentShow::class)->name('documents.show');
    // Halaman pengerjaan kuis (overview, mengerjakan, hasil).
    Route::get('quizzes/{quiz}', QuizRunner::class)->name('quizzes.show');
    // Halaman belajar flashcard (flip + spaced repetition) per dokumen.
    Route::get('documents/{document}/flashcards', FlashcardStudy::class)->name('flashcards.study');
});

// Chat with Document (RAG).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('chat', ChatIndex::class)->name('chat.index');
    Route::get('chat/{session}', ChatRoom::class)->name('chat.show');
});

// Riwayat Aktivitas — log pekerjaan AI (parse/chunk/embed/summarize/quiz_gen/flashcard_gen).
// User biasa hanya lihat job atas dokumennya sendiri; admin lihat semua (scoping di komponen).
Route::get('activity', ActivityHistory::class)
    ->middleware(['auth', 'verified'])
    ->name('activity.index');

// Export PDF — ringkasan dokumen & lembar kuis.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('documents/{document}/summary.pdf', [PdfExportController::class, 'summary'])
        ->name('documents.summary.pdf');
    Route::get('quizzes/{quiz}/export.pdf', [PdfExportController::class, 'quiz'])
        ->name('quizzes.export.pdf');
});

// Manajemen Pengguna — hanya admin (Gate manage-users di AppServiceProvider).
Route::get('admin/users', UserManager::class)
    ->middleware(['auth', 'verified', 'can:manage-users'])
    ->name('users.index');

require __DIR__.'/auth.php';
