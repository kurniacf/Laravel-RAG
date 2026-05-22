<?php

use App\Livewire\Chat\ChatIndex;
use App\Livewire\Chat\ChatRoom;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Document;
use App\Models\Subject;
use App\Models\User;
use App\Services\Rag\RagService;
use Livewire\Livewire;

/**
 * Helper: dokumen siap-chat (status ready + total_chunks > 0).
 */
function makeReadyChatDocument(User $user, ?Subject $subject = null): Document
{
    return Document::create([
        'user_id' => $user->id,
        'subject_id' => $subject?->id,
        'title' => 'Dokumen Siap Chat',
        'original_filename' => 'siap.pdf',
        'file_path' => 'documents/'.$user->id.'/siap.pdf',
        'file_size_bytes' => 1000,
        'status' => Document::STATUS_READY,
        'extracted_text' => 'Konten dummy.',
        'total_chunks' => 3,
        'processed_at' => now(),
    ]);
}

test('halaman chat index menolak tamu', function () {
    $this->get(route('chat.index'))->assertRedirect('/login');
});

test('user yang terverifikasi dapat membuka halaman chat index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('chat.index'))
        ->assertOk()
        ->assertSee('Chat dengan Dokumen');
});

test('user tanpa dokumen ter-vektorisasi melihat pesan kosong di modal', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ChatIndex::class)
        ->call('openStartModal')
        ->assertSee('Belum ada dokumen siap-chat');
});

test('user dapat memulai sesi chat baru dari dokumen ter-vektorisasi', function () {
    $user = User::factory()->create();
    $document = makeReadyChatDocument($user);

    $this->actingAs($user);

    Livewire::test(ChatIndex::class)
        ->call('openStartModal')
        ->set('selectedDocumentId', $document->id)
        ->call('startSession')
        ->assertRedirect();

    expect(ChatSession::count())->toBe(1);
    $session = ChatSession::first();
    expect($session->user_id)->toBe($user->id);
    expect($session->document_id)->toBe($document->id);
    expect($session->title)->toBe('Dokumen Siap Chat');
});

test('user tidak dapat memulai sesi chat dari dokumen yang belum ter-vektorisasi', function () {
    $user = User::factory()->create();
    $pendingDoc = Document::create([
        'user_id' => $user->id,
        'title' => 'Belum siap',
        'original_filename' => 'x.pdf',
        'file_path' => 'documents/'.$user->id.'/x.pdf',
        'file_size_bytes' => 100,
        'status' => Document::STATUS_READY,
        'total_chunks' => 0,
    ]);

    $this->actingAs($user);

    // Karena dokumen belum ter-vektorisasi, tidak muncul di availableDocuments,
    // jadi validasi exists akan tetap pass tetapi abort di startSession.
    Livewire::test(ChatIndex::class)
        ->set('selectedDocumentId', $pendingDoc->id)
        ->call('startSession')
        ->assertStatus(422);
});

test('user tidak dapat memulai sesi chat dari dokumen milik orang lain', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $document = makeReadyChatDocument($owner);

    $this->actingAs($other);

    Livewire::test(ChatIndex::class)
        ->set('selectedDocumentId', $document->id)
        ->call('startSession')
        ->assertStatus(403);
});

test('ChatRoom tidak dapat diakses oleh user lain yang bukan pemilik sesi', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $document = makeReadyChatDocument($owner);
    $session = ChatSession::create([
        'user_id' => $owner->id,
        'document_id' => $document->id,
        'title' => 'Privat',
    ]);

    $this->actingAs($intruder);

    Livewire::test(ChatRoom::class, ['session' => $session])
        ->assertStatus(403);
});

test('ChatRoom menyimpan dua pesan setelah ask: user message + assistant message', function () {
    $user = User::factory()->create();
    $document = makeReadyChatDocument($user);
    $session = ChatSession::create([
        'user_id' => $user->id,
        'document_id' => $document->id,
        'title' => 'Sesi uji',
    ]);

    $this->actingAs($user);

    // Mock RagService agar tidak panggil Gemini.
    $this->mock(RagService::class, function ($mock) {
        $mock->shouldReceive('ask')
            ->once()
            ->andReturn([
                'answer' => 'Ini jawaban dari mock RAG.',
                'cited_chunk_ids' => [10, 11],
                'tokens_used' => 123,
            ]);
    });

    Livewire::test(ChatRoom::class, ['session' => $session])
        ->set('question', 'Apa isi dokumen ini?')
        ->call('ask')
        ->assertHasNoErrors();

    $messages = ChatMessage::orderBy('id')->get();
    expect($messages)->toHaveCount(2);
    expect($messages[0]->role)->toBe(ChatMessage::ROLE_USER);
    expect($messages[0]->content)->toBe('Apa isi dokumen ini?');
    expect($messages[1]->role)->toBe(ChatMessage::ROLE_ASSISTANT);
    expect($messages[1]->content)->toBe('Ini jawaban dari mock RAG.');
    expect($messages[1]->cited_chunk_ids)->toBe([10, 11]);
    expect($messages[1]->tokens_used)->toBe(123);

    expect($session->fresh()->last_message_at)->not->toBeNull();
});

test('validasi: pertanyaan tidak boleh kosong', function () {
    $user = User::factory()->create();
    $document = makeReadyChatDocument($user);
    $session = ChatSession::create([
        'user_id' => $user->id,
        'document_id' => $document->id,
        'title' => 'X',
    ]);

    $this->actingAs($user);

    Livewire::test(ChatRoom::class, ['session' => $session])
        ->set('question', '')
        ->call('ask')
        ->assertHasErrors(['question']);
});
