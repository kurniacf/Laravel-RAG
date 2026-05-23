<?php

namespace App\Livewire;

use App\Models\AiJob;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Halaman "Riwayat Aktivitas" — daftar AI jobs (parse, chunk, embed, summarize,
 * quiz_gen, flashcard_gen) yang tercatat di tabel ai_jobs.
 *
 * Murni read-only ke DB, tidak memanggil Gemini. Hak akses:
 * - User biasa: hanya job yang dokumennya milik user tersebut.
 * - Admin: semua job, plus filter per user.
 */
#[Layout('layouts.app')]
#[Title('Riwayat Aktivitas')]
class ActivityHistory extends Component
{
    use WithPagination;

    /** Kata kunci pencarian — cocokkan ke judul / nama file dokumen. */
    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** Filter status: '' / pending / running / completed / failed. */
    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    /** Filter jenis job. */
    #[Url(as: 'type', except: '')]
    public string $typeFilter = '';

    /** Filter user — hanya berlaku untuk admin. */
    #[Url(as: 'user', except: '')]
    public string $userFilter = '';

    /** ID job yang sedang dibuka detailnya (modal). null = tertutup. */
    public ?int $detailId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingUserFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter', 'userFilter']);
        $this->resetPage();
    }

    /**
     * Query dasar — sudah mengaplikasikan role-scoping. Dipakai bersama untuk
     * paginator dan statistik agar angka konsisten.
     */
    protected function baseQuery(): Builder
    {
        $user = auth()->user();

        return AiJob::query()
            ->when(! $user->isAdmin(), function ($q) use ($user) {
                $q->whereHas('document', fn ($d) => $d->where('user_id', $user->id));
            });
    }

    /**
     * Query dengan filter pengguna (search/status/type/user) diaplikasikan.
     */
    protected function filteredQuery(): Builder
    {
        $user = auth()->user();
        $query = $this->baseQuery();

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->typeFilter !== '') {
            $query->where('job_type', $this->typeFilter);
        }

        if ($this->search !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $this->search).'%';
            $query->whereHas('document', function ($d) use ($term) {
                $d->where('title', 'like', $term)
                    ->orWhere('original_filename', 'like', $term);
            });
        }

        if ($user->isAdmin() && $this->userFilter !== '') {
            $query->whereHas('document', fn ($d) => $d->where('user_id', $this->userFilter));
        }

        return $query;
    }

    /**
     * Paginator job yang sudah difilter. Eager-load dokumen + relasinya untuk
     * cegah N+1 di view.
     */
    #[Computed]
    public function jobs()
    {
        return $this->filteredQuery()
            ->with(['document:id,user_id,subject_id,title,original_filename', 'document.subject:id,name,color_hex', 'document.user:id,name'])
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * Statistik 4 kartu di header — dihitung dari query DASAR (sebelum filter
     * UI) supaya angka mencerminkan totalnya yang sebenarnya, bukan total
     * yang sedang difilter.
     *
     * @return array{total: int, completed: int, failed: int, tokens: int}
     */
    #[Computed]
    public function stats(): array
    {
        $row = $this->baseQuery()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed", [AiJob::STATUS_COMPLETED])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS failed", [AiJob::STATUS_FAILED])
            ->selectRaw('COALESCE(SUM(tokens_used), 0) AS tokens')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'completed' => (int) ($row->completed ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'tokens' => (int) ($row->tokens ?? 0),
        ];
    }

    /**
     * Opsi pengguna untuk filter dropdown — hanya admin yang melihat.
     */
    #[Computed]
    public function userOptions()
    {
        if (! auth()->user()->isAdmin()) {
            return collect();
        }

        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Buka modal detail satu job. Pastikan job tersebut boleh diakses oleh
     * user (admin: bebas; user biasa: harus job atas dokumennya).
     */
    public function openDetail(int $id): void
    {
        $job = $this->baseQuery()->whereKey($id)->first();

        if ($job === null) {
            return;
        }

        $this->detailId = $job->id;
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
    }

    /**
     * Job yang sedang dibuka modal detailnya (null bila tidak ada).
     */
    #[Computed]
    public function detail(): ?AiJob
    {
        if ($this->detailId === null) {
            return null;
        }

        return $this->baseQuery()
            ->with(['document:id,user_id,title,original_filename', 'document.user:id,name'])
            ->whereKey($this->detailId)
            ->first();
    }

    public function render(): View
    {
        return view('livewire.activity-history');
    }
}
