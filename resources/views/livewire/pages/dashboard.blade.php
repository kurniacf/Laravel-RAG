<?php

use App\Models\FlashcardReview;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Dashboard')] class extends Component
{
    /** Statistik utama milik user. */
    public int $jumlahDokumen = 0;

    public int $jumlahKuisDikerjakan = 0;

    public ?int $rataRataSkor = null;

    public int $flashcardDikuasai = 0;

    /** Statistik agregat (admin). */
    public int $totalPengguna = 0;

    public int $totalDokumenSistem = 0;

    /** True bila user belum punya dokumen sama sekali (tampilkan empty state). */
    public bool $kosong = false;

    /** Data Chart 1 — aktivitas kuis 14 hari terakhir. */
    public array $aktivitasLabels = [];

    public array $aktivitasData = [];

    /** Data Chart 2 — skor 10 kuis terakhir. */
    public array $skorLabels = [];

    public array $skorData = [];

    /** Data Chart 3 — distribusi dokumen per mata pelajaran. */
    public array $subjekLabels = [];

    public array $subjekData = [];

    /** Daftar aktivitas terbaru user. */
    public array $aktivitasTerbaru = [];

    /**
     * Memuat seluruh data dashboard dalam satu mount. Query agregat dirancang
     * efisien (tanpa N+1): tiap sumber di-fetch sekali lalu diolah di PHP.
     */
    public function mount(): void
    {
        $user = auth()->user();
        $userId = $user->id;
        $isAdmin = $user->isAdmin();

        // ── Statistik utama ──
        $this->jumlahDokumen = DB::table('documents')
            ->when(! $isAdmin, fn ($q) => $q->where('user_id', $userId))
            ->count();

        $this->kosong = $this->jumlahDokumen === 0;

        $attempts = DB::table('quiz_attempts')
            ->where('user_id', $userId)
            ->where('status', 'completed');
        $this->jumlahKuisDikerjakan = (clone $attempts)->count();
        $avg = (clone $attempts)->avg('score');
        $this->rataRataSkor = $avg !== null ? (int) round($avg) : null;

        $this->flashcardDikuasai = DB::table('flashcard_reviews')
            ->where('user_id', $userId)
            ->where('repetitions', '>=', FlashcardReview::MASTERED_REPETITIONS)
            ->count();

        if ($isAdmin) {
            $this->totalPengguna = User::query()->count();
            $this->totalDokumenSistem = DB::table('documents')->count();
        }

        // User baru tanpa dokumen — tidak perlu menyiapkan chart.
        if ($this->kosong) {
            return;
        }

        $this->buildActivityChart($userId);
        $this->buildScoreChart($userId);
        $this->buildSubjectChart($userId, $isAdmin);
        $this->buildRecentActivity($userId, $isAdmin);
    }

    /** Chart 1: jumlah kuis dikerjakan per hari, 14 hari terakhir. */
    protected function buildActivityChart(int $userId): void
    {
        $perDay = DB::table('quiz_attempts')
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDays(13)->startOfDay())
            ->pluck('completed_at')
            ->map(fn ($ts) => Carbon::parse($ts)->toDateString())
            ->countBy();

        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $this->aktivitasLabels[] = $date->isoFormat('D MMM');
            $this->aktivitasData[] = (int) $perDay->get($date->toDateString(), 0);
        }
    }

    /** Chart 2: skor 10 kuis terakhir secara kronologis. */
    protected function buildScoreChart(int $userId): void
    {
        $recent = DB::table('quiz_attempts')
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(10)
            ->get(['score', 'completed_at']);

        foreach ($recent->reverse()->values() as $attempt) {
            $this->skorLabels[] = Carbon::parse($attempt->completed_at)->isoFormat('D MMM');
            $this->skorData[] = (int) $attempt->score;
        }
    }

    /** Chart 3: distribusi dokumen per mata pelajaran. */
    protected function buildSubjectChart(int $userId, bool $isAdmin): void
    {
        $bySubject = DB::table('documents')
            ->when(! $isAdmin, fn ($q) => $q->where('documents.user_id', $userId))
            ->leftJoin('subjects', 'documents.subject_id', '=', 'subjects.id')
            ->get(['subjects.name as subject_name'])
            ->countBy(fn ($row) => $row->subject_name ?: 'Tanpa Mata Pelajaran')
            ->sortDesc();

        foreach ($bySubject as $name => $count) {
            $this->subjekLabels[] = $name;
            $this->subjekData[] = $count;
        }
    }

    /** Gabungkan aktivitas terbaru dari beberapa sumber, ambil 6 teratas. */
    protected function buildRecentActivity(int $userId, bool $isAdmin): void
    {
        $items = collect();

        DB::table('documents')
            ->when(! $isAdmin, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['title', 'created_at'])
            ->each(fn ($d) => $items->push([
                'icon' => 'document',
                'text' => 'Mengunggah dokumen "'.$d->title.'"',
                'at' => Carbon::parse($d->created_at),
            ]));

        DB::table('quiz_attempts')
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(6)
            ->get(['score', 'completed_at'])
            ->each(fn ($a) => $items->push([
                'icon' => 'quiz',
                'text' => 'Menyelesaikan kuis dengan skor '.(int) $a->score,
                'at' => Carbon::parse($a->completed_at),
            ]));

        DB::table('summaries')
            ->join('documents', 'summaries.document_id', '=', 'documents.id')
            ->when(! $isAdmin, fn ($q) => $q->where('documents.user_id', $userId))
            ->orderByDesc('summaries.created_at')
            ->limit(6)
            ->get(['documents.title as doc_title', 'summaries.created_at as created_at'])
            ->each(fn ($s) => $items->push([
                'icon' => 'summary',
                'text' => 'Membuat ringkasan untuk "'.$s->doc_title.'"',
                'at' => Carbon::parse($s->created_at),
            ]));

        $this->aktivitasTerbaru = $items
            ->sortByDesc('at')
            ->take(6)
            ->map(fn ($item) => [
                'icon' => $item['icon'],
                'text' => $item['text'],
                'time' => $item['at']->diffForHumans(),
            ])
            ->values()
            ->all();
    }
}; ?>

@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $hariIni = now()->isoFormat('dddd, D MMMM Y');

    // Ikon untuk feed aktivitas terbaru (path Heroicons).
    $activityIcon = [
        'document' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
        'quiz' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'summary' => 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12',
    ];
@endphp

<div class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-8">

        {{-- ──────── Sapaan ──────── --}}
        <section>
            <p class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
                {{ $hariIni }}
            </p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-900">
                Halo, {{ $user->name }} <span class="text-2xl">👋</span>
            </h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-600">
                Ini ruang belajarmu. Unggah materi, ajukan pertanyaan, kerjakan kuis,
                pelajari flashcard, dan pantau progresmu di sini.
            </p>
        </section>

        {{-- ──────── Quick Actions ──────── --}}
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Mulai Cepat</h2>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <a href="{{ route('documents.index') }}" wire:navigate class="group flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md">
                    <span class="inline-flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-brand-50 text-brand-700 transition group-hover:bg-brand-100">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900">Unggah Dokumen</p>
                        <p class="mt-0.5 text-xs text-slate-500">Tambahkan PDF materi belajarmu.</p>
                    </div>
                </a>
                <a href="{{ route('chat.index') }}" wire:navigate class="group flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md">
                    <span class="inline-flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-brand-50 text-brand-700 transition group-hover:bg-brand-100">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900">Chat dengan Dokumen</p>
                        <p class="mt-0.5 text-xs text-slate-500">Tanya jawab berbasis materimu.</p>
                    </div>
                </a>
                <a href="{{ route('subjects.index') }}" wire:navigate class="group flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md">
                    <span class="inline-flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-amber-50 text-amber-700 transition group-hover:bg-amber-100">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900">
                            {{ $isAdmin ? 'Kelola Mata Pelajaran' : 'Jelajahi Mata Pelajaran' }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">Kategori belajar untuk dokumen.</p>
                    </div>
                </a>
            </div>
        </section>

        @if ($kosong)
            {{-- ──────── Empty state (user baru) ──────── --}}
            <section class="rounded-xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm">
                <span class="mx-auto inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </span>
                <h2 class="mt-4 text-lg font-semibold text-slate-900">Mulai perjalanan belajarmu</h2>
                <p class="mx-auto mt-1 max-w-md text-sm text-slate-600">
                    Statistik dan grafik progres akan muncul di sini setelah kamu mengunggah
                    dokumen pertama dan mulai belajar. Yuk, unggah materimu sekarang.
                </p>
                <a href="{{ route('documents.index') }}" wire:navigate class="mt-5 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Unggah Dokumen Pertama
                </a>
            </section>
        @else
            {{-- ──────── Kartu statistik ──────── --}}
            <section>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Statistik Belajar</h2>
                <div class="mt-3 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    @foreach ([
                        ['label' => $isAdmin ? 'Total Dokumen' : 'Dokumen Saya', 'value' => number_format($jumlahDokumen), 'tone' => 'brand'],
                        ['label' => 'Kuis Dikerjakan', 'value' => number_format($jumlahKuisDikerjakan), 'tone' => 'amber'],
                        ['label' => 'Rata-rata Skor Kuis', 'value' => $rataRataSkor !== null ? $rataRataSkor : '—', 'tone' => 'brand'],
                        ['label' => 'Flashcard Dikuasai', 'value' => number_format($flashcardDikuasai), 'tone' => 'amber'],
                    ] as $stat)
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                            <span @class([
                                'inline-flex h-10 w-10 items-center justify-center rounded-lg',
                                'bg-brand-50 text-brand-700' => $stat['tone'] === 'brand',
                                'bg-amber-50 text-amber-700' => $stat['tone'] === 'amber',
                            ])>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                </svg>
                            </span>
                            <p class="mt-3 text-sm font-medium text-slate-600">{{ $stat['label'] }}</p>
                            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $stat['value'] }}</p>
                        </article>
                    @endforeach
                </div>

                @if ($isAdmin)
                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-medium text-slate-500">Total Pengguna Terdaftar</p>
                            <p class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">{{ number_format($totalPengguna) }}</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-medium text-slate-500">Total Dokumen Sistem</p>
                            <p class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">{{ number_format($totalDokumenSistem) }}</p>
                        </article>
                    </div>
                @endif
            </section>

            {{-- ──────── Chart: aktivitas & skor ──────── --}}
            <section>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Analitik</h2>
                <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-2">

                    {{-- Chart 1 — aktivitas kuis. --}}
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">Aktivitas Kuis</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Jumlah kuis dikerjakan, 14 hari terakhir.</p>
                        @if (array_sum($aktivitasData) === 0)
                            <div class="flex h-64 items-center justify-center text-sm text-slate-400">Belum ada aktivitas kuis.</div>
                        @else
                            <div wire:ignore class="mt-3 h-64" x-data x-init="window.Chart && new Chart($refs.chartActivity, {
                                type: 'bar',
                                data: {
                                    labels: @js($aktivitasLabels),
                                    datasets: [{ label: 'Kuis', data: @js($aktivitasData), backgroundColor: '#059669', borderRadius: 4, maxBarThickness: 28 }],
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    plugins: { legend: { display: false } },
                                    scales: {
                                        y: { beginAtZero: true, ticks: { precision: 0, stepSize: 1 }, grid: { color: '#f1f5f9' } },
                                        x: { grid: { display: false } },
                                    },
                                },
                            })">
                                <canvas x-ref="chartActivity"></canvas>
                            </div>
                        @endif
                    </div>

                    {{-- Chart 2 — performa kuis. --}}
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">Performa Kuis</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Skor 10 kuis terakhir.</p>
                        @if (empty($skorData))
                            <div class="flex h-64 items-center justify-center text-sm text-slate-400">Belum ada kuis yang dikerjakan.</div>
                        @else
                            <div wire:ignore class="mt-3 h-64" x-data x-init="window.Chart && new Chart($refs.chartScore, {
                                type: 'line',
                                data: {
                                    labels: @js($skorLabels),
                                    datasets: [{
                                        label: 'Skor', data: @js($skorData),
                                        borderColor: '#059669', backgroundColor: 'rgba(5,150,105,0.12)',
                                        fill: true, tension: 0.3, pointRadius: 4, pointBackgroundColor: '#059669',
                                    }],
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    plugins: { legend: { display: false } },
                                    scales: {
                                        y: { beginAtZero: true, max: 100, grid: { color: '#f1f5f9' } },
                                        x: { grid: { display: false } },
                                    },
                                },
                            })">
                                <canvas x-ref="chartScore"></canvas>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            {{-- ──────── Chart subjek + Aktivitas terbaru ──────── --}}
            <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                {{-- Chart 3 — distribusi dokumen per mata pelajaran. --}}
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-900">Dokumen per Mata Pelajaran</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Sebaran koleksi dokumenmu.</p>
                    <div wire:ignore class="mt-3 h-64" x-data x-init="window.Chart && new Chart($refs.chartSubject, {
                        type: 'doughnut',
                        data: {
                            labels: @js($subjekLabels),
                            datasets: [{
                                data: @js($subjekData),
                                backgroundColor: ['#059669', '#f59e0b', '#3b82f6', '#8b5cf6', '#ef4444', '#14b8a6', '#ec4899', '#84cc16'],
                                borderWidth: 2, borderColor: '#ffffff',
                            }],
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } } },
                        },
                    })">
                        <canvas x-ref="chartSubject"></canvas>
                    </div>
                </div>

                {{-- Aktivitas terbaru. --}}
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-6 py-4">
                        <h3 class="text-sm font-semibold text-slate-900">Aktivitas Terbaru</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Jejak belajarmu yang paling akhir.</p>
                    </div>
                    @if (empty($aktivitasTerbaru))
                        <div class="px-6 py-12 text-center text-sm text-slate-400">Belum ada aktivitas tercatat.</div>
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($aktivitasTerbaru as $item)
                                <li class="flex items-center gap-3 px-6 py-3">
                                    <span class="inline-flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-4 w-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $activityIcon[$item['icon']] ?? $activityIcon['document'] }}" />
                                        </svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm text-slate-700">{{ $item['text'] }}</p>
                                        <p class="text-xs text-slate-400">{{ $item['time'] }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        @endif

    </div>
</div>
