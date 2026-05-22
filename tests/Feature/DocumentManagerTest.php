<?php

use App\Livewire\DocumentManager;
use App\Models\Document;
use App\Models\Subject;
use App\Models\User;
use App\Services\Documents\DocumentParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

test('halaman dokumen menolak tamu', function () {
    $this->get(route('documents.index'))->assertRedirect('/login');
});

test('user yang terverifikasi dapat mengakses halaman dokumen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('documents.index'))
        ->assertOk()
        ->assertSee('Dokumen');
});

test('validasi: hanya PDF yang diterima', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $nonPdf = UploadedFile::fake()->create('catatan.docx', 100, 'application/msword');

    Livewire::test(DocumentManager::class)
        ->set('title', 'Catatan')
        ->set('file', $nonPdf)
        ->call('submitUpload')
        ->assertHasErrors(['file']);
});

test('validasi: judul wajib diisi', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $pdf = UploadedFile::fake()->create('materi.pdf', 100, 'application/pdf');

    Livewire::test(DocumentManager::class)
        ->set('title', '')
        ->set('file', $pdf)
        ->call('submitUpload')
        ->assertHasErrors(['title']);
});

test('validasi: file melebihi 10 MB ditolak', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    // 11 MB (lebih dari batas 10 MB).
    $bigPdf = UploadedFile::fake()->create('besar.pdf', 11 * 1024, 'application/pdf');

    Livewire::test(DocumentManager::class)
        ->set('title', 'Besar')
        ->set('file', $bigPdf)
        ->call('submitUpload')
        ->assertHasErrors(['file']);
});

test('upload PDF valid: record dibuat, file tersimpan, parser dipanggil', function () {
    $user = User::factory()->create();
    $subject = Subject::factory()->create();

    $this->actingAs($user);

    $pdf = UploadedFile::fake()->create('materi.pdf', 100, 'application/pdf');

    // Mock parser agar test tidak butuh PDF asli.
    $this->mock(DocumentParser::class, function ($mock) {
        $mock->shouldReceive('parse')->once()->andReturnUsing(function (Document $doc) {
            $doc->update([
                'status' => Document::STATUS_READY,
                'page_count' => 5,
                'word_count' => 1200,
                'extracted_text' => 'Contoh teks hasil ekstraksi PDF untuk testing.',
                'processed_at' => now(),
            ]);

            return $doc->fresh();
        });
    });

    Livewire::test(DocumentManager::class)
        ->set('title', 'Bab 1 — Pengantar')
        ->set('subject_id', $subject->id)
        ->set('file', $pdf)
        ->call('submitUpload')
        ->assertHasNoErrors();

    $doc = Document::where('user_id', $user->id)->first();

    expect($doc)->not->toBeNull()
        ->title->toBe('Bab 1 — Pengantar')
        ->status->toBe(Document::STATUS_READY)
        ->subject_id->toBe($subject->id);

    Storage::disk('local')->assertExists($doc->file_path);
});

test('upload meng-increment documents_count pada subject yang dipilih', function () {
    $user = User::factory()->create();
    $subject = Subject::factory()->create(['documents_count' => 0]);

    $this->actingAs($user);

    $this->mock(DocumentParser::class, function ($mock) {
        $mock->shouldReceive('parse')->andReturnUsing(fn (Document $d) => $d->fresh());
    });

    $pdf = UploadedFile::fake()->create('m.pdf', 50, 'application/pdf');

    Livewire::test(DocumentManager::class)
        ->set('title', 'Test Count')
        ->set('subject_id', $subject->id)
        ->set('file', $pdf)
        ->call('submitUpload')
        ->assertHasNoErrors();

    expect($subject->fresh()->documents_count)->toBe(1);
});

test('user lain tidak boleh melihat detail dokumen orang lain', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $doc = Document::create([
        'user_id' => $owner->id,
        'title' => 'Rahasia',
        'original_filename' => 'rahasia.pdf',
        'file_path' => 'documents/'.$owner->id.'/dummy.pdf',
        'file_size_bytes' => 1024,
        'status' => Document::STATUS_READY,
    ]);

    $this->actingAs($intruder);

    Livewire::test(DocumentManager::class)
        ->call('openDetail', $doc->id)
        ->assertStatus(403);
});

test('admin boleh melihat detail dokumen pengguna lain', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $doc = Document::create([
        'user_id' => $owner->id,
        'title' => 'Materi Umum',
        'original_filename' => 'm.pdf',
        'file_path' => 'documents/'.$owner->id.'/d.pdf',
        'file_size_bytes' => 2048,
        'status' => Document::STATUS_READY,
    ]);

    $this->actingAs($admin);

    Livewire::test(DocumentManager::class)
        ->call('openDetail', $doc->id)
        ->assertSet('detailId', $doc->id);
});

test('hapus dokumen menghapus file fisik dan men-decrement counter subject', function () {
    $user = User::factory()->create();
    $subject = Subject::factory()->create(['documents_count' => 5]);

    // Buat file fisik dummy.
    Storage::disk('local')->put('documents/'.$user->id.'/test.pdf', 'fake pdf bytes');

    $doc = Document::create([
        'user_id' => $user->id,
        'subject_id' => $subject->id,
        'title' => 'Akan dihapus',
        'original_filename' => 'test.pdf',
        'file_path' => 'documents/'.$user->id.'/test.pdf',
        'file_size_bytes' => 14,
        'status' => Document::STATUS_READY,
    ]);

    $this->actingAs($user);

    Livewire::test(DocumentManager::class)
        ->call('confirmDelete', $doc->id)
        ->call('delete');

    expect(Document::find($doc->id))->toBeNull();
    expect($subject->fresh()->documents_count)->toBe(4);
    Storage::disk('local')->assertMissing('documents/'.$user->id.'/test.pdf');
});

test('user biasa tidak melihat dokumen milik orang lain di list', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Document::create([
        'user_id' => $userA->id,
        'title' => 'Milik A',
        'original_filename' => 'a.pdf',
        'file_path' => 'documents/'.$userA->id.'/a.pdf',
        'file_size_bytes' => 100,
        'status' => Document::STATUS_READY,
    ]);

    $this->actingAs($userB);

    Livewire::test(DocumentManager::class)
        ->assertDontSee('Milik A');
});

test('admin melihat semua dokumen di list', function () {
    $userA = User::factory()->create();
    $admin = User::factory()->admin()->create();

    Document::create([
        'user_id' => $userA->id,
        'title' => 'Dokumen Public',
        'original_filename' => 'p.pdf',
        'file_path' => 'documents/'.$userA->id.'/p.pdf',
        'file_size_bytes' => 100,
        'status' => Document::STATUS_READY,
    ]);

    $this->actingAs($admin);

    Livewire::test(DocumentManager::class)
        ->assertSee('Dokumen Public');
});
