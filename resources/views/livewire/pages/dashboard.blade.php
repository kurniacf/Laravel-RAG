<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Dashboard')] class extends Component
{
    public int $jumlahDokumen = 0;

    public int $jumlahMataPelajaran = 0;

    public int $jumlahPengguna = 0;

    /** Statistik fitur AI Tier 2. */
    public int $jumlahRingkasan = 0;

    public int $jumlahKuis = 0;

    public int $jumlahKuisDikerjakan = 0;

    /** Rata-rata skor kuis milik user (null bila belum pernah mengerjakan). */
    public ?int $rataRataSkor = null;

    /** @var array<int, object> */
    public array $dokumenTerbaru = [];

    /**
     * Memuat statistik + 5 dokumen terbaru. Pakai Schema::hasTable agar dashboard
     * tetap aman ditampilkan sebelum migrasi tabel-tabel terkait.
     */
    public function mount(): void
    {
        $user = auth()->user();

        if (Schema::hasTable('documents')) {
            $base = DB::table('documents')
                ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id));

            $this->jumlahDokumen = (clone $base)->count();

            // 5 dokumen terbaru, join subjects (opsional, bisa null).
            $this->dokumenTerbaru = $base
                ->leftJoin('subjects', 'documents.subject_id', '=', 'subjects.id')
                ->orderByDesc('documents.created_at')
                ->limit(5)
                ->get([
                    'documents.id',
                    'documents.title',
                    'documents.status',
                    'documents.page_count',
                    'documents.total_chunks',
                    'documents.created_at',
                    'subjects.name as subject_name',
                    'subjects.color_hex as subject_color',
                ])
                ->toArray();
        }

        if (Schema::hasTable('subjects')) {
            $this->jumlahMataPelajaran = DB::table('subjects')->count();
        }

        // Statistik Tier 2: ringkasan & kuis ikut scoping role (admin lihat semua);
        // kuis dikerjakan & rata-rata skor selalu milik user yang login.
        if (Schema::hasTable('summaries')) {
            $this->jumlahRingkasan = DB::table('summaries')
                ->join('documents', 'summaries.document_id', '=', 'documents.id')
                ->when(! $user->isAdmin(), fn ($q) => $q->where('documents.user_id', $user->id))
                ->count();
        }

        if (Schema::hasTable('quizzes')) {
            $this->jumlahKuis = DB::table('quizzes')
                ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
                ->count();
        }

        if (Schema::hasTable('quiz_attempts')) {
            $attempts = DB::table('quiz_attempts')
                ->where('user_id', $user->id)
                ->where('status', 'completed');

            $this->jumlahKuisDikerjakan = (clone $attempts)->count();

            $avg = (clone $attempts)->avg('score');
            $this->rataRataSkor = $avg !== null ? (int) round($avg) : null;
        }

        $this->jumlahPengguna = User::query()->count();
    }
}; ?>

@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();

    // Format tanggal hari ini dalam Bahasa Indonesia.
    $hariIni = now()->isoFormat('dddd, D MMMM Y');

    // Mapping kelas badge status (sinkron dengan DocumentManager).
    $statusBadge = [
        'pending'    => 'bg-slate-100 text-slate-700 ring-slate-600/20',
        'processing' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'ready'      => 'bg-brand-50 text-brand-700 ring-brand-600/20',
        'failed'     => 'bg-red-50 text-red-700 ring-red-600/20',
    ];
    $statusLabel = [
        'pending'    => 'Menunggu',
        'processing' => 'Memproses',
        'ready'      => 'Siap',
        'failed'     => 'Gagal',
    ];
@endphp

<div class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-8">

        {{-- ──────── Sapaan + tanggal ──────── --}}
        <section class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
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
                    Ini ruang belajarmu. Unggah materi, ajukan pertanyaan,
                    kerjakan kuis, dan biarkan AI membantu memetakan pemahamanmu.
                </p>
            </div>
        </section>

        {{-- ──────── Quick Actions ──────── --}}
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Mulai Cepat</h2>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-{{ $isAdmin ? 3 : 2 }}">
                <a
                    href="{{ route('documents.index') }}"
                    wire:navigate
                    class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md"
                >
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-brand-50 text-brand-700 transition group-hover:bg-brand-100">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-900">Unggah Dokumen</p>
                            <p class="mt-0.5 text-xs text-slate-500">Tambahkan PDF materi belajarmu.</p>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-none text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand-600">
                            <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </a>

                <a
                    href="{{ route('subjects.index') }}"
                    wire:navigate
                    class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md"
                >
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-amber-50 text-amber-700 transition group-hover:bg-amber-100">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-900">
                                {{ $isAdmin ? 'Kelola Mata Pelajaran' : 'Jelajahi Mata Pelajaran' }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $isAdmin ? 'Buat kategori belajar baru.' : 'Lihat kategori belajar yang tersedia.' }}
                            </p>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-none text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-amber-600">
                            <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </a>

                @if ($isAdmin)
                    <a
                        href="{{ route('users.index') }}"
                        wire:navigate
                        class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
                    >
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-slate-100 text-slate-700 transition group-hover:bg-slate-200">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-900">Kelola Pengguna</p>
                                <p class="mt-0.5 text-xs text-slate-500">CRUD akun + ubah peran.</p>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-none text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-700">
                                <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </a>
                @endif
            </div>
        </section>

        {{-- ──────── Statistik ──────── --}}
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ringkasan</h2>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-{{ $isAdmin ? 3 : 2 }}">

                {{-- Kartu: Dokumen --}}
                <article class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <span aria-hidden="true" class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-brand-500 via-brand-400 to-transparent"></span>
                    <div class="flex items-start justify-between gap-3">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-100/60 transition group-hover:bg-brand-100">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-4 text-sm font-medium text-slate-600">
                        {{ $isAdmin ? 'Total Dokumen' : 'Dokumen Saya' }}
                    </p>
                    <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">
                        {{ number_format($jumlahDokumen) }}
                    </p>
                    <p class="mt-2 text-xs text-slate-500">
                        Unggah materi PDF untuk mulai belajar.
                    </p>
                </article>

                {{-- Kartu: Mata Pelajaran --}}
                <article class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <span aria-hidden="true" class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-amber-500 via-amber-400 to-transparent"></span>
                    <div class="flex items-start justify-between gap-3">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-700 ring-1 ring-amber-100/60 transition group-hover:bg-amber-100">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-4 text-sm font-medium text-slate-600">Mata Pelajaran</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">
                        {{ number_format($jumlahMataPelajaran) }}
                    </p>
                    <p class="mt-2 text-xs text-slate-500">
                        Kelompokkan dokumen ke kategori belajar.
                    </p>
                </article>

                @if ($isAdmin)
                    {{-- Kartu: Pengguna (admin saja) --}}
                    <article class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <span aria-hidden="true" class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-slate-500 via-slate-400 to-transparent"></span>
                        <div class="flex items-start justify-between gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-700 ring-1 ring-slate-200/60 transition group-hover:bg-slate-200">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                </svg>
                            </span>
                        </div>
                        <p class="mt-4 text-sm font-medium text-slate-600">Pengguna Terdaftar</p>
                        <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">
                            {{ number_format($jumlahPengguna) }}
                        </p>
                        <p class="mt-2 text-xs text-slate-500">
                            Akun aktif lintas semua peran.
                        </p>
                    </article>
                @endif

            </div>
        </section>

        {{-- ──────── Aktivitas Belajar (Tier 2) ──────── --}}
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Aktivitas Belajar</h2>
            <div class="mt-3 grid grid-cols-2 gap-4 lg:grid-cols-4">

                {{-- Ringkasan dibuat --}}
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                        </svg>
                    </span>
                    <p class="mt-3 text-sm font-medium text-slate-600">Ringkasan Dibuat</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ number_format($jumlahRingkasan) }}</p>
                </article>

                {{-- Kuis dibuat --}}
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </span>
                    <p class="mt-3 text-sm font-medium text-slate-600">Kuis Dibuat</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ number_format($jumlahKuis) }}</p>
                </article>

                {{-- Kuis dikerjakan --}}
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                        </svg>
                    </span>
                    <p class="mt-3 text-sm font-medium text-slate-600">Kuis Dikerjakan</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ number_format($jumlahKuisDikerjakan) }}</p>
                </article>

                {{-- Rata-rata skor --}}
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                    </span>
                    <p class="mt-3 text-sm font-medium text-slate-600">Rata-rata Skor Kuis</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">
                        {{ $rataRataSkor !== null ? $rataRataSkor : '—' }}
                    </p>
                </article>

            </div>
        </section>

        {{-- ──────── Dokumen Terbaru ──────── --}}
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Dokumen Terbaru</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Lima dokumen yang baru saja {{ $isAdmin ? 'diunggah di sistem' : 'kamu unggah' }}.
                    </p>
                </div>
                <a
                    href="{{ route('documents.index') }}"
                    wire:navigate
                    class="inline-flex items-center gap-1 text-xs font-semibold text-brand-700 transition hover:text-brand-800"
                >
                    Lihat semua
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                        <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                    </svg>
                </a>
            </div>

            @if (count($dokumenTerbaru) === 0)
                {{-- Empty state ramah. --}}
                <div class="px-6 py-12 text-center">
                    <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-6 w-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                    </span>
                    <p class="mt-3 text-sm font-medium text-slate-700">Belum ada dokumen</p>
                    <p class="mt-1 text-xs text-slate-500">
                        Mulai dengan mengunggah PDF materi pertamamu.
                    </p>
                    <a
                        href="{{ route('documents.index') }}"
                        wire:navigate
                        class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Unggah Dokumen
                    </a>
                </div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($dokumenTerbaru as $doc)
                        <li class="flex items-center gap-4 px-6 py-4 transition hover:bg-slate-50/60">
                            <span class="inline-flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-900">{{ $doc->title }}</p>
                                <div class="mt-0.5 flex items-center gap-2 text-xs text-slate-500">
                                    @if ($doc->subject_name)
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-medium" style="background-color: {{ $doc->subject_color ?? '#059669' }}1a; color: {{ $doc->subject_color ?? '#059669' }};">
                                            {{ $doc->subject_name }}
                                        </span>
                                    @endif
                                    @if ($doc->page_count)
                                        <span>{{ $doc->page_count }} hal</span>
                                        <span class="text-slate-300">·</span>
                                    @endif
                                    @if ($doc->total_chunks > 0)
                                        <span>{{ $doc->total_chunks }} chunks</span>
                                        <span class="text-slate-300">·</span>
                                    @endif
                                    <span>{{ \Carbon\Carbon::parse($doc->created_at)->diffForHumans() }}</span>
                                </div>
                            </div>

                            <span class="hidden flex-none items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset sm:inline-flex {{ $statusBadge[$doc->status] ?? '' }}">
                                {{ $statusLabel[$doc->status] ?? $doc->status }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- ──────── Yang akan datang ──────── --}}
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-brand-50 text-brand-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                    </svg>
                </span>
                <h3 class="text-base font-semibold text-slate-900">Yang akan datang</h3>
            </div>
            <p class="mt-1 text-sm text-slate-600">
                Fitur AI yang sedang dikembangkan untuk PintarBelajar AI.
            </p>
            <ul class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ([
                    ['Flashcard Otomatis', 'Poin kunci dari dokumen untuk repetisi terjadwal.'],
                    ['Analitik Belajar', 'Pemetaan progres pemahaman lintas dokumen dan kuis.'],
                ] as $i => [$title, $desc])
                    <li class="flex items-start gap-3 rounded-lg border border-slate-100 bg-slate-50/50 p-3 transition hover:bg-white">
                        <span class="mt-0.5 inline-flex h-6 w-6 flex-none items-center justify-center rounded-full bg-white text-xs font-semibold text-brand-700 ring-1 ring-brand-200">
                            {{ $i + 1 }}
                        </span>
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ $title }}</p>
                            <p class="text-xs text-slate-500">{{ $desc }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

    </div>
</div>
