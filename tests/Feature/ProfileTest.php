<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get('/profile');

    $response
        ->assertOk()
        ->assertSeeVolt('profile.update-profile-information-form')
        ->assertSeeVolt('profile.update-password-form')
        ->assertSeeVolt('profile.delete-user-form');
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.update-profile-information-form')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $component
        ->assertHasNoErrors()
        ->assertNoRedirect();

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.update-profile-information-form')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $component
        ->assertHasNoErrors()
        ->assertNoRedirect();

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $component
        ->assertHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $component
        ->assertHasErrors('password')
        ->assertNoRedirect();

    $this->assertNotNull($user->fresh());
});

test('kata sandi dapat diperbarui dengan kata sandi lama yang benar', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Volt::test('profile.update-password-form')
        ->set('current_password', 'password')
        ->set('password', 'KataSandiBaru2026')
        ->set('password_confirmation', 'KataSandiBaru2026')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('KataSandiBaru2026', $user->refresh()->password))->toBeTrue();
});

test('ganti kata sandi gagal bila kata sandi lama salah', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Volt::test('profile.update-password-form')
        ->set('current_password', 'kata-sandi-keliru')
        ->set('password', 'KataSandiBaru2026')
        ->set('password_confirmation', 'KataSandiBaru2026')
        ->call('updatePassword')
        ->assertHasErrors('current_password');
});

test('admin terakhir tidak dapat menghapus akunnya sendiri', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Volt::test('profile.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasErrors('password');

    expect(User::find($admin->id))->not->toBeNull();
});

test('admin bukan terakhir tetap dapat menghapus akunnya', function () {
    User::factory()->admin()->create(); // admin lain yang tersisa
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Volt::test('profile.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect(User::find($admin->id))->toBeNull();
});

test('halaman profil menampilkan section informasi akun', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/profile')
        ->assertOk()
        ->assertSeeVolt('profile.account-info');
});
