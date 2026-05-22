<?php

use App\Livewire\Admin\UserManager;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('halaman manajemen pengguna menolak user biasa', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertForbidden();
});

test('halaman manajemen pengguna menerima admin', function () {
    $this->actingAs($this->admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertSee('Manajemen Pengguna');
});

test('admin dapat membuat pengguna baru dengan role yang dipilih', function () {
    $this->actingAs($this->admin);

    Livewire::test(UserManager::class)
        ->call('openCreate')
        ->set('name', 'Pengguna Baru')
        ->set('email', 'baru@pintarbelajar.test')
        ->set('password', 'rahasia12345')
        ->set('password_confirmation', 'rahasia12345')
        ->set('role', User::ROLE_USER)
        ->call('save')
        ->assertHasNoErrors();

    expect(User::where('email', 'baru@pintarbelajar.test')->first())
        ->not->toBeNull()
        ->role->toBe(User::ROLE_USER)
        ->name->toBe('Pengguna Baru');
});

test('admin dapat mempromosi user menjadi admin lewat edit', function () {
    $this->actingAs($this->admin);

    $target = User::factory()->create(['role' => User::ROLE_USER]);

    Livewire::test(UserManager::class)
        ->call('openEdit', $target->id)
        ->set('role', User::ROLE_ADMIN)
        ->call('save')
        ->assertHasNoErrors();

    expect($target->fresh()->role)->toBe(User::ROLE_ADMIN);
});

test('admin dapat menghapus pengguna lain', function () {
    $this->actingAs($this->admin);

    $target = User::factory()->create();

    Livewire::test(UserManager::class)
        ->call('confirmDelete', $target->id)
        ->call('delete');

    expect(User::find($target->id))->toBeNull();
});

test('admin tidak dapat menghapus dirinya sendiri', function () {
    $this->actingAs($this->admin);

    Livewire::test(UserManager::class)
        ->call('confirmDelete', $this->admin->id);

    // Self-delete dicegah lebih awal.
    expect(User::find($this->admin->id))->not->toBeNull();
});

test('admin terakhir tidak dapat dihapus oleh admin lain', function () {
    // Buat admin kedua, hapus admin pertama, lalu pastikan jumlah admin = 1.
    $this->actingAs($this->admin);

    $otherAdmin = User::factory()->admin()->create();
    $this->actingAs($otherAdmin);

    Livewire::test(UserManager::class)
        ->call('confirmDelete', $this->admin->id)
        ->call('delete');

    expect(User::find($this->admin->id))->toBeNull();
    expect(User::where('role', User::ROLE_ADMIN)->count())->toBe(1);
});

test('pencarian memfilter pengguna berdasarkan nama', function () {
    $this->actingAs($this->admin);

    User::factory()->create(['name' => 'Budi Santoso']);
    User::factory()->create(['name' => 'Citra Lestari']);

    Livewire::test(UserManager::class)
        ->set('search', 'Budi')
        ->assertSee('Budi Santoso')
        ->assertDontSee('Citra Lestari');
});

test('filter role memfilter pengguna berdasarkan peran', function () {
    $this->actingAs($this->admin);

    User::factory()->admin()->create(['name' => 'Admin Lain']);
    User::factory()->create(['role' => User::ROLE_USER, 'name' => 'User Biasa']);

    Livewire::test(UserManager::class)
        ->set('roleFilter', User::ROLE_ADMIN)
        ->assertSee('Admin Lain')
        ->assertDontSee('User Biasa');
});

test('validasi: email tidak boleh duplikat', function () {
    $this->actingAs($this->admin);

    User::factory()->create(['email' => 'duplikat@example.com']);

    Livewire::test(UserManager::class)
        ->call('openCreate')
        ->set('name', 'Penguji')
        ->set('email', 'duplikat@example.com')
        ->set('password', 'rahasia12345')
        ->set('password_confirmation', 'rahasia12345')
        ->set('role', User::ROLE_USER)
        ->call('save')
        ->assertHasErrors(['email']);
});

test('validasi: password wajib saat create namun opsional saat update', function () {
    $this->actingAs($this->admin);

    // Create tanpa password → error.
    Livewire::test(UserManager::class)
        ->call('openCreate')
        ->set('name', 'Tanpa Password')
        ->set('email', 'no-pass@example.com')
        ->set('role', User::ROLE_USER)
        ->call('save')
        ->assertHasErrors(['password']);

    // Update tanpa password → boleh.
    $existing = User::factory()->create();
    Livewire::test(UserManager::class)
        ->call('openEdit', $existing->id)
        ->set('name', 'Nama Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($existing->fresh()->name)->toBe('Nama Baru');
});
