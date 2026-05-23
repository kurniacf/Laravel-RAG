<?php

namespace App\Livewire;

use App\Models\Document;
use App\Models\Quiz;
use App\Models\Subject;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Pencarian global di topbar — mencari dokumen, mata pelajaran, dan kuis
 * dalam satu kotak. Tampil di semua halaman aplikasi via layout `layouts.app`.
 *
 * Hak akses:
 * - User biasa: dokumen & kuis hanya miliknya. Subject tetap dilihat semua
 *   (sesuai CLAUDE.md §5: user boleh lihat daftar subject).
 * - Admin: bebas (tidak di-scope).
 *
 * Tidak memanggil AI sama sekali. Murni query DB dengan LIKE + limit 5 per
 * kategori untuk dropdown.
 */
class GlobalSearch extends Component
{
    /** Minimum karakter sebelum query dieksekusi. */
    public const MIN_QUERY_LENGTH = 2;

    /** Maks hasil per kategori (cegah dropdown raksasa). */
    public const RESULTS_PER_CATEGORY = 5;

    public string $query = '';

    /** Apakah dropdown sedang dibuka di sisi klien (di-bind ke Alpine). */
    public bool $open = false;

    public function clear(): void
    {
        $this->query = '';
        $this->open = false;
    }

    public function close(): void
    {
        $this->open = false;
    }

    /**
     * Apakah query sudah cukup panjang untuk dieksekusi.
     */
    public function isQueryReady(): bool
    {
        return mb_strlen(trim($this->query)) >= self::MIN_QUERY_LENGTH;
    }

    /**
     * Dokumen yang cocok dengan query. Sudah di-scope per role.
     *
     * @return \Illuminate\Support\Collection<int, Document>
     */
    #[Computed]
    public function documents()
    {
        if (! $this->isQueryReady()) {
            return collect();
        }

        $user = auth()->user();
        $term = $this->likeTerm();

        return Document::query()
            ->select(['id', 'user_id', 'subject_id', 'title', 'original_filename', 'status', 'total_chunks'])
            ->with('subject:id,name,color_hex')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('original_filename', 'like', $term);
            })
            ->orderByDesc('id')
            ->limit(self::RESULTS_PER_CATEGORY)
            ->get();
    }

    /**
     * Mata pelajaran yang cocok dengan query (semua user boleh).
     *
     * @return \Illuminate\Support\Collection<int, Subject>
     */
    #[Computed]
    public function subjects()
    {
        if (! $this->isQueryReady()) {
            return collect();
        }

        $term = $this->likeTerm();

        return Subject::query()
            ->select(['id', 'name', 'slug', 'color_hex', 'documents_count'])
            ->where('name', 'like', $term)
            ->orderBy('name')
            ->limit(self::RESULTS_PER_CATEGORY)
            ->get();
    }

    /**
     * Kuis yang cocok (judul atau dokumen induknya). Scope role lewat dokumen.
     *
     * @return \Illuminate\Support\Collection<int, Quiz>
     */
    #[Computed]
    public function quizzes()
    {
        if (! $this->isQueryReady()) {
            return collect();
        }

        $user = auth()->user();
        $term = $this->likeTerm();

        return Quiz::query()
            ->select(['id', 'document_id', 'user_id', 'title', 'difficulty', 'question_count'])
            ->with('document:id,user_id,title')
            ->when(! $user->isAdmin(), function ($q) use ($user) {
                $q->whereHas('document', fn ($d) => $d->where('user_id', $user->id));
            })
            ->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhereHas('document', fn ($d) => $d->where('title', 'like', $term));
            })
            ->orderByDesc('id')
            ->limit(self::RESULTS_PER_CATEGORY)
            ->get();
    }

    /**
     * Total jumlah hasil dari semua kategori.
     */
    #[Computed]
    public function totalResults(): int
    {
        return $this->documents->count()
            + $this->subjects->count()
            + $this->quizzes->count();
    }

    /**
     * Bangun pola LIKE dengan escape `%` dan `_` agar tidak memicu wildcard
     * tak sengaja saat user mengetik karakter tersebut.
     */
    protected function likeTerm(): string
    {
        $escaped = str_replace(['%', '_'], ['\%', '\_'], trim($this->query));

        return '%'.$escaped.'%';
    }

    public function render(): View
    {
        return view('livewire.global-search');
    }
}
