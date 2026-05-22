<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public string $email = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Perbarui nama & email user. Bila email berubah, status verifikasi
     * di-reset (mekanisme bawaan Breeze).
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Kirim ulang email verifikasi (hanya relevan bila app mewajibkan verifikasi).
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-6 py-4">
        <h2 class="flex items-center gap-2 text-base font-semibold text-slate-900">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5 text-brand-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            Informasi Profil
        </h2>
        <p class="mt-0.5 text-xs text-slate-500">Perbarui nama dan alamat email akunmu.</p>
    </div>

    <form wire:submit="updateProfileInformation" class="space-y-5 px-6 py-5">
        <div>
            <x-input-label for="name" value="Nama lengkap" />
            <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1.5 block w-full" required autocomplete="name" />
            <x-input-error class="mt-1.5" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="Alamat email" />
            <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1.5 block w-full" required autocomplete="username" />
            <x-input-error class="mt-1.5" :messages="$errors->get('email')" />

            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div class="mt-2">
                    <p class="text-sm text-slate-700">
                        Alamat email kamu belum terverifikasi.
                        <button type="button" wire:click.prevent="sendVerification" class="font-medium text-brand-700 underline transition hover:text-brand-800">
                            Kirim ulang tautan verifikasi.
                        </button>
                    </p>
                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-sm font-medium text-brand-700">
                            Tautan verifikasi baru telah dikirim ke alamat emailmu.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label value="Peran" />
            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <span @class([
                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
                    'bg-brand-50 text-brand-700 ring-brand-600/20' => auth()->user()->isAdmin(),
                    'bg-slate-100 text-slate-700 ring-slate-600/20' => auth()->user()->isUser(),
                ])>
                    {{ auth()->user()->roleLabel() }}
                </span>
                <span class="text-xs text-slate-400">Peran hanya dapat diubah oleh admin.</span>
            </div>
        </div>

        <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
            <x-primary-button>Simpan Perubahan</x-primary-button>
            <x-action-message class="text-sm font-medium text-brand-700" on="profile-updated">
                Tersimpan.
            </x-action-message>
        </div>
    </form>
</section>
