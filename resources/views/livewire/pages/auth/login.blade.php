<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest'), Title('Masuk')] class extends Component
{
    public LoginForm $form;

    /**
     * Memproses permintaan login.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-8">
        <h2 class="text-2xl font-semibold tracking-tight text-slate-900">
            Masuk ke akun Anda
        </h2>
        <p class="mt-2 text-sm text-slate-600">
            Selamat datang kembali. Silakan masukkan kredensial Anda untuk
            melanjutkan belajar.
        </p>
    </div>

    {{-- Notifikasi status sesi (mis. setelah reset password). --}}
    <x-auth-session-status class="mb-4 rounded-lg bg-brand-50 px-4 py-3 text-sm font-medium text-brand-800" :status="session('status')" />

    <form wire:submit="login" class="space-y-5">
        <div>
            <x-input-label for="email" value="Alamat email" />
            <x-text-input
                wire:model="form.email"
                id="email"
                class="mt-1.5"
                type="email"
                name="email"
                placeholder="nama@email.com"
                required
                autofocus
                autocomplete="username"
            />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" value="Kata sandi" />
                @if (Route::has('password.request'))
                    <a
                        class="text-xs font-medium text-brand-700 underline-offset-2 transition hover:text-brand-800 hover:underline"
                        href="{{ route('password.request') }}"
                        wire:navigate
                    >
                        Lupa kata sandi?
                    </a>
                @endif
            </div>
            <x-text-input
                wire:model="form.password"
                id="password"
                class="mt-1.5"
                type="password"
                name="password"
                placeholder="Masukkan kata sandi"
                required
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <label for="remember" class="flex items-center gap-2 select-none">
            <input
                wire:model="form.remember"
                id="remember"
                type="checkbox"
                name="remember"
                class="h-4 w-4 rounded border-slate-300 text-brand-600 shadow-sm focus:ring-brand-600/30"
            >
            <span class="text-sm text-slate-600">Ingat saya di perangkat ini</span>
        </label>

        <x-primary-button class="w-full">
            <span wire:loading.remove wire:target="login">Masuk</span>
            <span wire:loading wire:target="login" class="inline-flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4l3-3-3-3v4a8 8 0 1 0 8 8h-4l3 3 3-3h-4a8 8 0 0 1-8 8z"></path>
                </svg>
                Memproses...
            </span>
        </x-primary-button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-600">
        Belum punya akun?
        <a
            href="{{ route('register') }}"
            wire:navigate
            class="font-semibold text-brand-700 transition hover:text-brand-800"
        >
            Daftar di sini
        </a>
    </p>
</div>
