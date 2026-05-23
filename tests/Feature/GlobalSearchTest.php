<?php

use App\Livewire\GlobalSearch;
use App\Models\Document;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use Livewire\Livewire;

function makeSearchDocument(User $user, string $title, ?int $subjectId = null): Document
{
    return Document::create([
        'user_id' => $user->id,
        'subject_id' => $subjectId,
        'title' => $title,
        'original_filename' => 'd.pdf',
        'file_path' => 'documents/'.$user->id.'/'.uniqid().'.pdf',
        'file_size_bytes' => 100,
        'status' => Document::STATUS_READY,
    ]);
}

function makeSearchQuiz(Document $document, string $title): Quiz
{
    return Quiz::create([
        'document_id' => $document->id,
        'user_id' => $document->user_id,
        'title' => $title,
        'difficulty' => Quiz::DIFFICULTY_MEDIUM,
        'question_count' => 5,
    ]);
}

test('query kosong tidak mengeksekusi pencarian', function () {
    $user = User::factory()->create();
    makeSearchDocument($user, 'Matematika Bab 1');

    $this->actingAs($user);

    $component = Livewire::test(GlobalSearch::class);

    expect($component->get('documents')->isEmpty())->toBeTrue();
    expect($component->get('subjects')->isEmpty())->toBeTrue();
    expect($component->get('quizzes')->isEmpty())->toBeTrue();
});

test('query satu karakter belum cukup untuk mengeksekusi pencarian', function () {
    $user = User::factory()->create();
    makeSearchDocument($user, 'Materi A');

    $this->actingAs($user);

    Livewire::test(GlobalSearch::class)
        ->set('query', 'a')
        ->assertSet('totalResults', 0);
});

test('user biasa hanya menemukan dokumen miliknya', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    makeSearchDocument($alice, 'Materi Bersama Alice');
    makeSearchDocument($bob, 'Materi Bersama Bob');

    $this->actingAs($alice);

    Livewire::test(GlobalSearch::class)
        ->set('query', 'Bersama')
        ->assertSee('Materi Bersama Alice')
        ->assertDontSee('Materi Bersama Bob');
});

test('admin menemukan dokumen dari semua pengguna', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    makeSearchDocument($user, 'Materi Bersama Sekali');

    $this->actingAs($admin);

    Livewire::test(GlobalSearch::class)
        ->set('query', 'Bersama')
        ->assertSee('Materi Bersama Sekali');
});

test('pencarian mata pelajaran terlihat oleh semua user', function () {
    $user = User::factory()->create();
    Subject::create([
        'name' => 'Biologi Molekuler',
        'color_hex' => '#059669',
    ]);

    $this->actingAs($user);

    Livewire::test(GlobalSearch::class)
        ->set('query', 'Biologi')
        ->assertSee('Biologi Molekuler');
});

test('pencarian kuis dibatasi sesuai role', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $aliceDoc = makeSearchDocument($alice, 'Materi Alice Untuk Kuis');
    $bobDoc = makeSearchDocument($bob, 'Materi Bob Untuk Kuis');

    makeSearchQuiz($aliceDoc, 'Kuis Fotosintesis Alice');
    makeSearchQuiz($bobDoc, 'Kuis Fotosintesis Bob');

    $this->actingAs($alice);

    Livewire::test(GlobalSearch::class)
        ->set('query', 'Fotosintesis')
        ->assertSee('Kuis Fotosintesis Alice')
        ->assertDontSee('Kuis Fotosintesis Bob');
});

test('hasil dikelompokkan dan total results dihitung benar', function () {
    $user = User::factory()->create();

    $subject = Subject::create(['name' => 'Kalkulus Lanjut', 'color_hex' => '#059669']);
    $doc = makeSearchDocument($user, 'Materi Kalkulus Dasar', $subject->id);
    makeSearchQuiz($doc, 'Kuis Kalkulus 1');

    $this->actingAs($user);

    $component = Livewire::test(GlobalSearch::class)
        ->set('query', 'kalkulus');

    expect($component->get('documents')->count())->toBe(1);
    expect($component->get('subjects')->count())->toBe(1);
    expect($component->get('quizzes')->count())->toBe(1);
    expect($component->get('totalResults'))->toBe(3);
});

test('hasil per kategori dibatasi 5 entri', function () {
    $user = User::factory()->create();

    // 7 dokumen — harus tetap dibatasi 5 di hasil.
    for ($i = 1; $i <= 7; $i++) {
        makeSearchDocument($user, "Dokumen Spesimen Nomor {$i}");
    }

    $this->actingAs($user);

    $component = Livewire::test(GlobalSearch::class)
        ->set('query', 'Spesimen');

    expect($component->get('documents')->count())->toBe(GlobalSearch::RESULTS_PER_CATEGORY);
});

test('empty state tampil saat query >= 2 karakter tapi tidak ada hasil', function () {
    $user = User::factory()->create();
    makeSearchDocument($user, 'Materi Apapun');

    $this->actingAs($user);

    Livewire::test(GlobalSearch::class)
        ->set('query', 'tidakadayangcocok')
        ->assertSet('totalResults', 0)
        ->assertSee('Tidak ditemukan hasil');
});

test('escape wildcard LIKE: karakter persen tidak memicu match wildcard', function () {
    $user = User::factory()->create();
    makeSearchDocument($user, 'Materi Statistik');

    $this->actingAs($user);

    // Tanpa escape, '%a%' akan match semua. Setelah escape, '%a%' akan dicari
    // sebagai substring literal "%a%" yang tentu tidak match "Materi Statistik".
    Livewire::test(GlobalSearch::class)
        ->set('query', '%a%')
        ->assertSet('totalResults', 0);
});

test('clear membersihkan query', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(GlobalSearch::class)
        ->set('query', 'tes')
        ->set('open', true)
        ->call('clear')
        ->assertSet('query', '')
        ->assertSet('open', false);
});
