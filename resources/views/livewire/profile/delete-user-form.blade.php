<?php

use App\Livewire\Actions\Logout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Hapus akun user yang sedang login setelah konfirmasi kata sandi.
     *
     * Proteksi: admin terakhir tidak boleh menghapus dirinya sendiri agar
     * sistem selalu punya minimal satu pengelola.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        $user = Auth::user();

        if ($user->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            $this->addError('password', 'Kamu adalah admin terakhir. Akun ini tidak dapat dihapus demi menjaga akses pengelolaan sistem.');

            return;
        }

        tap($user, $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="overflow-hidden rounded-xl border border-red-200 bg-white shadow-sm">
    <div class="border-b border-red-100 bg-red-50/60 px-6 py-4">
        <h2 class="flex items-center gap-2 text-base font-semibold text-red-900">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5 text-red-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            Zona Berbahaya
        </h2>
        <p class="mt-0.5 text-xs text-red-700/80">Tindakan berikut bersifat permanen dan tidak dapat dibatalkan.</p>
    </div>

    <div class="px-6 py-5" x-data>
        <p class="text-sm text-slate-600">
            Menghapus akun akan menghilangkan seluruh dokumen, ringkasan, kuis,
            dan riwayat chat milikmu secara permanen. Pastikan kamu sudah
            menyimpan data penting sebelum melanjutkan.
        </p>
        <x-danger-button
            class="mt-4"
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        >
            Hapus Akun Saya
        </x-danger-button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">
            <h2 class="text-base font-semibold text-slate-900">
                Yakin ingin menghapus akun?
            </h2>

            <p class="mt-1 text-sm text-slate-600">
                Setelah dihapus, seluruh data akunmu hilang permanen. Masukkan
                kata sandi untuk mengonfirmasi.
            </p>

            <div class="mt-5">
                <x-input-label for="password" value="Kata sandi" class="sr-only" />
                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="block w-full"
                    placeholder="Kata sandi"
                />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                <x-danger-button>
                    <span wire:loading.remove wire:target="deleteUser">Ya, Hapus Akun</span>
                    <span wire:loading wire:target="deleteUser">Menghapus...</span>
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
