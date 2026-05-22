<?php

use App\Models\User;

test('dashboard menolak tamu', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});

test('dashboard user menampilkan nama, kartu Dokumen Saya, dan Mata Pelajaran (tanpa kartu Pengguna)', function () {
    $user = User::factory()->create([
        'name' => 'Penguji Dashboard',
        'role' => User::ROLE_USER,
    ]);

    $this->actingAs($user);

    $response = $this->get('/dashboard');

    $response
        ->assertOk()
        ->assertSee('Penguji Dashboard')
        ->assertSee('Dokumen Saya')
        ->assertSee('Mata Pelajaran')
        ->assertDontSee('Pengguna Terdaftar');
});

test('dashboard admin menampilkan kartu Pengguna Terdaftar dan label Total Dokumen', function () {
    User::factory()->count(3)->create();

    $admin = User::factory()->admin()->create([
        'name' => 'Penguji Admin',
    ]);

    $this->actingAs($admin);

    $response = $this->get('/dashboard');

    $response
        ->assertOk()
        ->assertSee('Penguji Admin')
        ->assertSee('Total Dokumen')
        ->assertSee('Pengguna Terdaftar');
});

test('dashboard menampilkan seksi statistik Tier 2', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Aktivitas Belajar')
        ->assertSee('Ringkasan Dibuat')
        ->assertSee('Kuis Dibuat')
        ->assertSee('Rata-rata Skor Kuis');
});
