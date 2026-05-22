<?php

namespace App\Livewire;

use App\Models\Document;
use App\Models\Subject;
use App\Services\Documents\DocumentParser;
use App\Services\Rag\DocumentIndexer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Dokumen')]
class DocumentManager extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    /** Form upload. */
    public $file = null;

    public string $title = '';

    public ?int $subject_id = null;

    public bool $showUploadModal = false;

    /** Konfirmasi hapus. */
    public ?int $deletingId = null;

    public ?string $deletingTitle = null;

    public bool $showDeleteModal = false;

    /**
     * Paginator dokumen sesuai role: admin lihat semua, lain hanya miliknya.
     */
    #[Computed]
    public function documents()
    {
        $user = auth()->user();

        return Document::query()
            ->with('subject:id,name,color_hex,icon')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->when($this->search !== '', function ($q) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $this->search).'%';
                $q->where('title', 'like', $term);
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->paginate(10);
    }

    #[Computed]
    public function subjectOptions()
    {
        return Subject::query()->orderBy('name')->get(['id', 'name']);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openUpload(): void
    {
        $this->reset(['file', 'title', 'subject_id']);
        $this->resetErrorBag();
        $this->showUploadModal = true;
    }

    public function closeUpload(): void
    {
        $this->reset(['file', 'title', 'subject_id', 'showUploadModal']);
        $this->resetErrorBag();
    }

    /**
     * Submit upload. Validasi → simpan file → buat record →
     * jalankan parser (queue sync, langsung di request ini).
     *
     * Sebelum return: dispatch 'document-uploaded' agar stepper Alpine
     * di view bisa beralih ke tahap "Selesai".
     *
     * CATATAN: method ini SENGAJA tidak bernama `upload()` karena nama itu
     * konflik dengan Livewire JS built-in `$wire.upload()` (helper file
     * upload). Saat `wire:submit="upload"`, Alpine memanggil JS helper, bukan
     * method server. Selalu pakai nama lain untuk method form submit.
     */
    public function submitUpload(DocumentParser $parser): void
    {
        $maxKb = (int) (Document::MAX_FILE_SIZE / 1024);

        $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'file' => ['required', 'file', 'mimes:pdf', "max:{$maxKb}"],
        ]);

        $user = auth()->user();

        // Simpan file dengan nama UUID untuk privasi + hindari collision.
        $extension = strtolower($this->file->getClientOriginalExtension() ?: 'pdf');
        $path = $this->file->storeAs(
            sprintf('documents/%d', $user->id),
            Str::uuid()->toString().".{$extension}",
            'local',
        );

        $document = Document::create([
            'user_id' => $user->id,
            'subject_id' => $this->subject_id ?: null,
            'title' => $this->title,
            'original_filename' => $this->file->getClientOriginalName(),
            'file_path' => $path,
            'file_size_bytes' => $this->file->getSize(),
            'status' => Document::STATUS_PENDING,
        ]);

        // Karena queue=sync, parser jalan langsung di request ini.
        $document = $parser->parse($document);

        // Update denormalized counter di subjects.
        if ($document->subject_id) {
            Subject::where('id', $document->subject_id)->increment('documents_count');
        }

        $message = $document->isReady()
            ? "Dokumen \"{$document->title}\" berhasil diunggah dan diproses."
            : "Dokumen tersimpan namun pemrosesan gagal: {$document->error_message}";

        session()->flash($document->isReady() ? 'status' : 'error', $message);

        // Beri tahu klien tahapan sudah tuntas, lalu tutup modal.
        $this->dispatch('document-uploaded', success: $document->isReady());

        $this->closeUpload();
        $this->resetPage();
    }

    /**
     * Trigger eksplisit untuk indexing ke vector store (chunk + embed).
     * Hanya boleh untuk dokumen yang sudah status=ready dan belum terindeks.
     */
    public function indexToVector(int $id, DocumentIndexer $indexer): void
    {
        $document = $this->findOwned($id);

        if (! $document->isReady()) {
            session()->flash('error', 'Hanya dokumen dengan status "Siap" yang dapat diproses ke vector.');

            return;
        }

        try {
            $indexer->index($document);
            $fresh = $document->fresh();
            session()->flash(
                'status',
                "Dokumen \"{$fresh->title}\" telah diproses menjadi {$fresh->total_chunks} chunk vector."
            );
        } catch (Throwable $e) {
            session()->flash('error', 'Gagal memproses ke vector: '.$e->getMessage());
        }
    }

    public function confirmDelete(int $id): void
    {
        $document = $this->findOwned($id);

        $this->deletingId = $document->id;
        $this->deletingTitle = $document->title;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $document = $this->findOwned($this->deletingId);

        // Hapus file fisik bila ada.
        if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }

        if ($document->subject_id) {
            Subject::where('id', $document->subject_id)->decrement('documents_count');
        }

        $document->delete();

        session()->flash('status', "Dokumen \"{$this->deletingTitle}\" telah dihapus.");

        $this->deletingId = null;
        $this->deletingTitle = null;
        $this->showDeleteModal = false;
    }

    public function closeDeleteModal(): void
    {
        $this->deletingId = null;
        $this->deletingTitle = null;
        $this->showDeleteModal = false;
    }

    /**
     * Ambil dokumen dengan pemeriksaan kepemilikan: user biasa hanya bisa
     * memanipulasi dokumen sendiri; admin bisa semua.
     */
    protected function findOwned(int $id): Document
    {
        $user = auth()->user();
        $document = Document::findOrFail($id);

        abort_if(! $user->isAdmin() && $document->user_id !== $user->id, 403);

        return $document;
    }

    public function render(): View
    {
        return view('livewire.document-manager');
    }
}
