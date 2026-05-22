<?php

use App\Models\Document;
use App\Models\QuizAttempt;
use App\Models\Summary;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public int $jumlahDokumen = 0;

    public int $jumlahRingkasan = 0;

    public int $jumlahKuisDikerjakan = 0;

    /** Rata-rata skor kuis (null bila belum pernah mengerjakan). */
    public ?int $rataRataSkor = null;

    /**
     * Muat statistik milik user yang sedang login (read-only).
     */
    public function mount(): void
    {
        $userId = Auth::id();

        $this->jumlahDokumen = Document::where('user_id', $userId)->count();

        $this->jumlahRingkasan = Summary::whereHas(
            'document',
            fn ($query) => $query->where('user_id', $userId),
        )->count();

        $attempts = QuizAttempt::where('user_id', $userId)
            ->where('status', QuizAttempt::STATUS_COMPLETED);

        $this->jumlahKuisDikerjakan = (clone $attempts)->count();

        $avg = (clone $attempts)->avg('score');
        $this->rataRataSkor = $avg !== null ? (int) round($avg) : null;
    }
}; ?>

@php $u = auth()->user(); @endphp

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-6 py-4">
        <h2 class="flex items-center gap-2 text-base font-semibold text-slate-900">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5 text-brand-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
            </svg>
            Informasi Akun
        </h2>
        <p class="mt-0.5 text-xs text-slate-500">Ringkasan keanggotaan dan aktivitas belajarmu.</p>
    </div>

    <div class="px-6 py-5">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Bergabung sejak</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900">
                    {{ $u->created_at?->translatedFormat('d F Y') ?? '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Login terakhir</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900">
                    {{ $u->last_login_at ? $u->last_login_at->translatedFormat('d F Y, H:i') : 'Belum tercatat' }}
                </dd>
            </div>
        </dl>

        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs font-medium text-slate-500">Dokumen</p>
                <p class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">{{ number_format($jumlahDokumen) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs font-medium text-slate-500">Ringkasan</p>
                <p class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">{{ number_format($jumlahRingkasan) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs font-medium text-slate-500">Kuis Dikerjakan</p>
                <p class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">{{ number_format($jumlahKuisDikerjakan) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs font-medium text-slate-500">Rata-rata Skor</p>
                <p class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">{{ $rataRataSkor !== null ? $rataRataSkor : '—' }}</p>
            </div>
        </div>
    </div>
</section>
