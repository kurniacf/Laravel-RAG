<?php

use App\Livewire\SubjectManager;
use App\Models\Subject;
use App\Models\User;
use Livewire\Livewire;

test('semua role yang terverifikasi dapat melihat halaman mata pelajaran', function () {
    foreach ([User::ROLE_USER, User::ROLE_ADMIN] as $role) {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('subjects.index'))
            ->assertOk()
            ->assertSee('Mata Pelajaran');
    }
});

test('user biasa tidak melihat tombol tambah', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $this->actingAs($user)
        ->get(route('subjects.index'))
        ->assertDontSee('Tambah Mata Pelajaran');
});

test('admin melihat tombol tambah', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('subjects.index'))
        ->assertSee('Tambah Mata Pelajaran');
});

test('user biasa tidak dapat membuat mata pelajaran (gate cegah)', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $this->actingAs($user);

    Livewire::test(SubjectManager::class)
        ->call('openCreate')
        ->assertStatus(403);
});

test('admin dapat membuat mata pelajaran dengan slug auto', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(SubjectManager::class)
        ->call('openCreate')
        ->set('name', 'Matematika Dasar')
        ->set('icon', 'calculator')
        ->set('color_hex', '#2563eb')
        ->call('save')
        ->assertHasNoErrors();

    $subject = Subject::where('name', 'Matematika Dasar')->first();
    expect($subject)
        ->not->toBeNull()
        ->slug->toBe('matematika-dasar')
        ->icon->toBe('calculator')
        ->color_hex->toBe('#2563eb');
});

test('admin dapat mengedit dan slug ikut update bila name berubah', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    $subject = Subject::factory()->create(['name' => 'Bahasa Awal', 'slug' => 'bahasa-awal']);

    Livewire::test(SubjectManager::class)
        ->call('openEdit', $subject->id)
        ->set('name', 'Bahasa Indonesia')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $subject->fresh();
    expect($fresh->name)->toBe('Bahasa Indonesia');
    expect($fresh->slug)->toBe('bahasa-indonesia');
});

test('admin dapat menghapus mata pelajaran', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    $subject = Subject::factory()->create();

    Livewire::test(SubjectManager::class)
        ->call('confirmDelete', $subject->id)
        ->call('delete');

    expect(Subject::find($subject->id))->toBeNull();
});

test('slug unik dengan suffix bila nama bentrok', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Subject::create(['name' => 'Fisika', 'slug' => 'fisika', 'color_hex' => '#059669']);

    Livewire::test(SubjectManager::class)
        ->call('openCreate')
        ->set('name', 'Fisika')
        ->set('icon', 'beaker')
        ->set('color_hex', '#059669')
        ->call('save')
        ->assertHasNoErrors();

    expect(Subject::pluck('slug')->all())
        ->toContain('fisika')
        ->toContain('fisika-2');
});

test('validasi: name wajib', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(SubjectManager::class)
        ->call('openCreate')
        ->set('name', '')
        ->set('icon', 'book')
        ->set('color_hex', '#059669')
        ->call('save')
        ->assertHasErrors(['name']);
});

test('pencarian memfilter berdasarkan nama', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Subject::factory()->create(['name' => 'Sejarah Nusantara']);
    Subject::factory()->create(['name' => 'Pemrograman Web']);

    Livewire::test(SubjectManager::class)
        ->set('search', 'Sejarah')
        ->assertSee('Sejarah Nusantara')
        ->assertDontSee('Pemrograman Web');
});
