<?php

use App\Livewire\DocumentShow;
use App\Models\Document;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('gemini.api_key', 'dummy-test-key');
});

/**
 * Dokumen siap (ready + punya teks) milik $owner.
 */
function makeShowDocument(?User $owner = null): Document
{
    $owner ??= User::factory()->create();

    return Document::create([
        'user_id' => $owner->id,
        'title' => 'Materi Detail Uji',
        'original_filename' => 'detail.pdf',
        'file_path' => 'documents/'.$owner->id.'/detail.pdf',
        'file_size_bytes' => 4096,
        'status' => Document::STATUS_READY,
        'extracted_text' => 'Isi materi yang cukup panjang untuk diringkas oleh AI.',
        'page_count' => 4,
        'word_count' => 50,
        'processed_at' => now(),
    ]);
}

/**
 * Fake respons Gemini generateContent.
 */
function fakeGeminiGenerate(string $text = 'Ringkasan hasil AI.'): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => $text]]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['totalTokenCount' => 40],
        ], 200),
    ]);
}

test('halaman detail dokumen menolak tamu', function () {
    $doc = makeShowDocument();

    $this->get(route('documents.show', $doc))->assertRedirect('/login');
});

test('pemilik dapat membuka halaman detail dokumennya', function () {
    $owner = User::factory()->create();
    $doc = makeShowDocument($owner);

    $this->actingAs($owner)
        ->get(route('documents.show', $doc))
        ->assertOk()
        ->assertSee('Materi Detail Uji');
});

test('user lain tidak boleh membuka detail dokumen orang lain', function () {
    $doc = makeShowDocument();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->get(route('documents.show', $doc))
        ->assertForbidden();
});

test('admin boleh membuka detail dokumen pengguna lain', function () {
    $doc = makeShowDocument();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('documents.show', $doc))
        ->assertOk();
});

test('generateSummary membuat tiga ringkasan untuk dokumen ready', function () {
    fakeGeminiGenerate();
    $owner = User::factory()->create();
    $doc = makeShowDocument($owner);

    $this->actingAs($owner);

    Livewire::test(DocumentShow::class, ['document' => $doc])
        ->call('generateSummary')
        ->assertHasNoErrors();

    expect(Summary::where('document_id', $doc->id)->count())->toBe(3);
});

test('halaman menampilkan ringkasan tersimpan tanpa memanggil AI lagi', function () {
    // Http di-fake; bila ada panggilan tak terduga, assertNothingSent akan gagal.
    Http::fake();

    $owner = User::factory()->create();
    $doc = makeShowDocument($owner);

    foreach (Summary::TYPES as $type) {
        Summary::create([
            'document_id' => $doc->id,
            'type' => $type,
            'content' => 'Ringkasan tersimpan untuk '.$type,
            'word_count' => 4,
        ]);
    }

    $this->actingAs($owner);

    Livewire::test(DocumentShow::class, ['document' => $doc])
        ->assertOk()
        ->assertSee('Ringkasan tersimpan untuk executive');

    Http::assertNothingSent();
});

test('generateSummary pada dokumen belum ready memunculkan pesan kesalahan', function () {
    $owner = User::factory()->create();
    $doc = Document::create([
        'user_id' => $owner->id,
        'title' => 'Belum Siap',
        'original_filename' => 'x.pdf',
        'file_path' => 'documents/'.$owner->id.'/x.pdf',
        'file_size_bytes' => 100,
        'status' => Document::STATUS_PENDING,
    ]);

    $this->actingAs($owner);

    Livewire::test(DocumentShow::class, ['document' => $doc])
        ->call('generateSummary')
        ->assertSee('Hanya dokumen berstatus');

    expect(Summary::where('document_id', $doc->id)->count())->toBe(0);
});

test('panel teks ekstraksi menampilkan jumlah kata untuk dokumen ber-teks', function () {
    $owner = User::factory()->create();
    $doc = makeShowDocument($owner);

    $this->actingAs($owner);

    Livewire::test(DocumentShow::class, ['document' => $doc])
        ->assertOk()
        ->assertSee('Teks Hasil Ekstraksi')
        ->assertSee('kata');
});

test('panel teks ekstraksi menampilkan empty state bila dokumen tanpa teks', function () {
    $owner = User::factory()->create();
    $doc = Document::create([
        'user_id' => $owner->id,
        'title' => 'Tanpa Teks',
        'original_filename' => 'kosong.pdf',
        'file_path' => 'documents/'.$owner->id.'/kosong.pdf',
        'file_size_bytes' => 100,
        'status' => Document::STATUS_PENDING,
    ]);

    $this->actingAs($owner);

    Livewire::test(DocumentShow::class, ['document' => $doc])
        ->assertOk()
        ->assertSee('Belum ada teks hasil ekstraksi');
});
