<?php

namespace App\Livewire\Chat;

use App\Models\ChatSession;
use App\Models\Document;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Halaman index Chat: list sesi yang user punya, tombol "Chat baru" yang
 * membuka modal pemilihan dokumen ter-vektorisasi.
 */
#[Layout('layouts.app')]
#[Title('Chat')]
class ChatIndex extends Component
{
    public bool $showStartModal = false;

    public ?int $selectedDocumentId = null;

    /**
     * Daftar sesi chat milik user (admin lihat semua).
     */
    #[Computed]
    public function sessions()
    {
        $user = auth()->user();

        return ChatSession::query()
            ->with(['document:id,title', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->withCount('messages')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    /**
     * Dokumen siap-chat (sudah ter-vektorisasi).
     */
    #[Computed]
    public function availableDocuments()
    {
        $user = auth()->user();

        return Document::query()
            ->where('status', Document::STATUS_READY)
            ->where('total_chunks', '>', 0)
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('title')
            ->get(['id', 'title', 'total_chunks']);
    }

    public function openStartModal(): void
    {
        $this->selectedDocumentId = null;
        $this->showStartModal = true;
    }

    public function closeStartModal(): void
    {
        $this->showStartModal = false;
        $this->selectedDocumentId = null;
    }

    /**
     * Mulai sesi chat baru untuk dokumen yang dipilih. Redirect ke ChatRoom.
     */
    public function startSession(): void
    {
        $this->validate([
            'selectedDocumentId' => ['required', 'integer', 'exists:documents,id'],
        ]);

        $user = auth()->user();
        $document = Document::findOrFail($this->selectedDocumentId);

        // User biasa hanya boleh chat dengan dokumen miliknya sendiri.
        abort_if(! $user->isAdmin() && $document->user_id !== $user->id, 403);
        abort_if($document->total_chunks === 0, 422, 'Dokumen belum diproses ke vector.');

        $session = ChatSession::create([
            'user_id' => $user->id,
            'document_id' => $document->id,
            'title' => $document->title,
            'last_message_at' => null,
        ]);

        $this->redirect(route('chat.show', $session->id), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.chat.chat-index');
    }
}
