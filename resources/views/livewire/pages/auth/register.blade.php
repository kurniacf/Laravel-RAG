<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest'), Title('Daftar')] class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Memproses permintaan pendaftaran. User baru otomatis mendapat role
     * 'user' (lihat default kolom role di tabel users).
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['role'] = User::ROLE_USER;

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-8">
        <h2 class="text-2xl font-semibold tracking-tight text-slate-900">
            Buat akun baru
        </h2>
        <p class="mt-2 text-sm text-slate-600">
            Daftar gratis untuk mulai mengunggah materi dan biarkan AI membantu
            Anda memahami lebih dalam.
        </p>
    </div>

    <form wire:submit="register" class="space-y-5">
        <div>
            <x-input-label for="name" value="Nama lengkap" />
            <x-text-input
                wire:model="name"
                id="name"
                class="mt-1.5"
                type="text"
                name="name"
                placeholder="Nama Anda"
                required
                autofocus
                autocomplete="name"
            />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" value="Alamat email" />
            <x-text-input
                wire:model="email"
                id="email"
                class="mt-1.5"
                type="email"
                name="email"
                placeholder="nama@email.com"
                required
                autocomplete="username"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Kata sandi" />
            <x-text-input
                wire:model="password"
                id="password"
                class="mt-1.5"
                type="password"
                name="password"
                placeholder="Minimal 8 karakter"
                required
                autocomplete="new-password"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Konfirmasi kata sandi" />
            <x-text-input
                wire:model="password_confirmation"
                id="password_confirmation"
                class="mt-1.5"
                type="password"
                name="password_confirmation"
                placeholder="Ulangi kata sandi"
                required
                autocomplete="new-password"
            />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">
            <span wire:loading.remove wire:target="register">Buat akun</span>
            <span wire:loading wire:target="register" class="inline-flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4l3-3-3-3v4a8 8 0 1 0 8 8h-4l3 3 3-3h-4a8 8 0 0 1-8 8z"></path>
                </svg>
                Mendaftarkan...
            </span>
        </x-primary-button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-600">
        Sudah punya akun?
        <a
            href="{{ route('login') }}"
            wire:navigate
            class="font-semibold text-brand-700 transition hover:text-brand-800"
        >
            Masuk di sini
        </a>
    </p>
</div>
