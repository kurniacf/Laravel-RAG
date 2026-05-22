<?php

namespace App\Livewire\Chat;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\DocumentChunk;
use App\Services\Rag\RagService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * Halaman chat: tampilkan riwayat pesan untuk satu sesi, terima pertanyaan,
 * panggil RagService, simpan jawaban + citation, render ulang.
 */
#[Layout('layouts.app')]
#[Title('Chat')]
class ChatRoom extends Component
{
    public ChatSession $session;

    public string $question = '';

    public ?string $errorMessage = null;

    public function mount(ChatSession $session): void
    {
        $user = auth()->user();
        abort_if(! $user->isAdmin() && $session->user_id !== $user->id, 403);

        $this->session = $session->load(['document', 'messages']);
    }

    /**
     * Map id chunk → preview content untuk modal citation di view.
     *
     * @return array<int, array{id:int, chunk_index:int, content:string}>
     */
    #[Computed]
    public function chunkLookup(): array
    {
        $messageIds = $this->session->messages->pluck('cited_chunk_ids')->flatten()->filter()->unique()->all();

        if (empty($messageIds)) {
            return [];
        }

        return DocumentChunk::query()
            ->whereIn('id', $messageIds)
            ->get(['id', 'chunk_index', 'content'])
            ->keyBy('id')
            ->map(fn ($c) => [
                'id' => $c->id,
                'chunk_index' => $c->chunk_index,
                'content' => $c->content,
            ])
            ->all();
    }

    /**
     * Kirim pertanyaan, panggil RagService, simpan dua ChatMessage (user & assistant).
     *
     * Riwayat 6 pesan terakhir dikirim ke RagService agar query rewriting
     * dapat mengisi konteks follow-up ("kenapa?", "lalu?", dst).
     */
    public function ask(RagService $rag): void
    {
        $this->validate([
            'question' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $this->errorMessage = null;
        $userQuestion = trim($this->question);
        $this->question = '';

        // 1) Simpan pesan user. Pesan user selalu source=document (kolom hanya
        //    bermakna untuk assistant; default mempermudah query analitik).
        ChatMessage::create([
            'chat_session_id' => $this->session->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => $userQuestion,
        ]);

        try {
            // 2) Panggil RagService dengan riwayat 6 pesan terakhir SEBELUM
            //    pesan ini (untuk konteks follow-up).
            $history = $this->session->messages()
                ->where('id', '<', ChatMessage::max('id') ?? PHP_INT_MAX)
                ->latest('id')
                ->limit(6)
                ->get()
                ->reverse()
                ->values();

            $result = $rag->ask($this->session->document, $userQuestion, $history);

            // 3) Simpan jawaban assistant termasuk label source.
            ChatMessage::create([
                'chat_session_id' => $this->session->id,
                'role' => ChatMessage::ROLE_ASSISTANT,
                'content' => $result['answer'],
                'source' => $result['source'] ?? ChatMessage::SOURCE_DOCUMENT,
                'cited_chunk_ids' => $result['cited_chunk_ids'],
                'tokens_used' => $result['tokens_used'],
            ]);

            $this->session->update(['last_message_at' => now()]);
        } catch (Throwable $e) {
            $this->errorMessage = 'Gagal mendapatkan jawaban dari AI: '.$e->getMessage();
            // Biarkan pesan user tetap, agar user tidak kehilangan pertanyaannya.
        }

        $this->session->load('messages');

        $this->dispatch('chat-scroll-bottom');
    }

    public function render(): View
    {
        return view('livewire.chat.chat-room');
    }
}
