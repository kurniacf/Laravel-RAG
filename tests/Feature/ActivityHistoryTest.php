<?php

use App\Livewire\ActivityHistory;
use App\Models\AiJob;
use App\Models\Document;
use App\Models\User;
use Livewire\Livewire;

/** Buat satu dokumen ready milik user. */
function makeActivityDocument(User $user, string $title = 'Dokumen'): Document
{
    return Document::create([
        'user_id' => $user->id,
        'title' => $title,
        'original_filename' => 'd.pdf',
        'file_path' => 'documents/'.$user->id.'/'.uniqid().'.pdf',
        'file_size_bytes' => 100,
        'status' => Document::STATUS_READY,
    ]);
}

function makeAiJob(Document $document, string $type = AiJob::TYPE_PARSE, string $status = AiJob::STATUS_COMPLETED, ?int $tokens = null): AiJob
{
    return AiJob::create([
        'document_id' => $document->id,
        'job_type' => $type,
        'status' => $status,
        'started_at' => now()->subSeconds(2),
        'finished_at' => now(),
        'duration_ms' => 1500,
        'tokens_used' => $tokens,
    ]);
}

test('halaman aktivitas menolak tamu', function () {
    $this->get(route('activity.index'))->assertRedirect('/login');
});

test('user terverifikasi dapat membuka halaman aktivitas', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('activity.index'))
        ->assertOk()
        ->assertSee('Riwayat Aktivitas');
});

test('user biasa hanya melihat job atas dokumennya sendiri', function () {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    $aliceDoc = makeActivityDocument($alice, 'Materi Alice');
    $bobDoc = makeActivityDocument($bob, 'Materi Bob');

    makeAiJob($aliceDoc, AiJob::TYPE_PARSE);
    makeAiJob($bobDoc, AiJob::TYPE_PARSE);

    $this->actingAs($alice);

    Livewire::test(ActivityHistory::class)
        ->assertSee('Materi Alice')
        ->assertDontSee('Materi Bob');
});

test('admin dapat melihat job dari semua pengguna', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $userDoc = makeActivityDocument($user, 'Materi User');
    makeAiJob($userDoc, AiJob::TYPE_SUMMARIZE);

    $this->actingAs($admin);

    Livewire::test(ActivityHistory::class)
        ->assertSee('Materi User');
});

test('statistik header dihitung dari job yang terlihat user', function () {
    $user = User::factory()->create();
    $doc = makeActivityDocument($user);

    makeAiJob($doc, AiJob::TYPE_PARSE, AiJob::STATUS_COMPLETED, 100);
    makeAiJob($doc, AiJob::TYPE_EMBED, AiJob::STATUS_COMPLETED, 250);
    makeAiJob($doc, AiJob::TYPE_QUIZ_GEN, AiJob::STATUS_FAILED);

    $this->actingAs($user);

    $stats = Livewire::test(ActivityHistory::class)->get('stats');

    expect($stats['total'])->toBe(3)
        ->and($stats['completed'])->toBe(2)
        ->and($stats['failed'])->toBe(1)
        ->and($stats['tokens'])->toBe(350);
});

test('filter status menyaring baris yang ditampilkan', function () {
    $user = User::factory()->create();
    // Pakai dokumen berbeda agar judul tabel unik per baris — tidak bentrok
    // dengan label opsi di dropdown filter.
    $docCompleted = makeActivityDocument($user, 'Dokumen Selesai Saja');
    $docFailed = makeActivityDocument($user, 'Dokumen Yang Gagal');

    makeAiJob($docCompleted, AiJob::TYPE_PARSE, AiJob::STATUS_COMPLETED);
    makeAiJob($docFailed, AiJob::TYPE_EMBED, AiJob::STATUS_FAILED);

    $this->actingAs($user);

    Livewire::test(ActivityHistory::class)
        ->set('statusFilter', AiJob::STATUS_FAILED)
        ->assertSee('Dokumen Yang Gagal')
        ->assertDontSee('Dokumen Selesai Saja');
});

test('filter jenis pekerjaan jalan', function () {
    $user = User::factory()->create();
    $docParse = makeActivityDocument($user, 'Dokumen Khusus Parse');
    $docSummary = makeActivityDocument($user, 'Dokumen Khusus Ringkasan');

    makeAiJob($docParse, AiJob::TYPE_PARSE);
    makeAiJob($docSummary, AiJob::TYPE_SUMMARIZE);

    $this->actingAs($user);

    Livewire::test(ActivityHistory::class)
        ->set('typeFilter', AiJob::TYPE_SUMMARIZE)
        ->assertSee('Dokumen Khusus Ringkasan')
        ->assertDontSee('Dokumen Khusus Parse');
});

test('search nama dokumen menyaring baris', function () {
    $user = User::factory()->create();
    $matematika = makeActivityDocument($user, 'Matematika Bab 1');
    $sejarah = makeActivityDocument($user, 'Sejarah Indonesia');

    makeAiJob($matematika);
    makeAiJob($sejarah);

    $this->actingAs($user);

    Livewire::test(ActivityHistory::class)
        ->set('search', 'matematika')
        ->assertSee('Matematika Bab 1')
        ->assertDontSee('Sejarah Indonesia');
});

test('modal detail hanya menampilkan job yang user boleh akses', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $bobDoc = makeActivityDocument($bob, 'Materi Bob');
    $bobJob = makeAiJob($bobDoc, AiJob::TYPE_PARSE, AiJob::STATUS_FAILED);
    $bobJob->update(['error_message' => 'Rahasia milik Bob']);

    $this->actingAs($alice);

    Livewire::test(ActivityHistory::class)
        ->call('openDetail', $bobJob->id)
        ->assertSet('detailId', null)
        ->assertDontSee('Rahasia milik Bob');
});

test('detail job menampilkan pesan error bila status failed', function () {
    $user = User::factory()->create();
    $doc = makeActivityDocument($user, 'Materi Error');
    $job = makeAiJob($doc, AiJob::TYPE_EMBED, AiJob::STATUS_FAILED);
    $job->update(['error_message' => 'Gemini API: rate limit terlampaui']);

    $this->actingAs($user);

    Livewire::test(ActivityHistory::class)
        ->call('openDetail', $job->id)
        ->assertSet('detailId', $job->id)
        ->assertSee('Gemini API: rate limit terlampaui');
});
