@php
    use App\Models\User as UserModel;

    $rolePillClasses = [
        UserModel::ROLE_ADMIN => 'bg-brand-50 text-brand-700 ring-brand-600/20',
        UserModel::ROLE_USER  => 'bg-slate-100 text-slate-700 ring-slate-600/20',
    ];

    $roleLabels = [
        UserModel::ROLE_ADMIN => 'Admin',
        UserModel::ROLE_USER  => 'User',
    ];
@endphp

<div class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">

        {{-- Header halaman + tombol tambah. --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Administrasi</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                    Manajemen Pengguna
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    Kelola akun pengguna, peran akses, dan kredensial.
                </p>
            </div>
            <x-primary-button type="button" wire:click="openCreate">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Pengguna
            </x-primary-button>
        </header>

        {{-- Flash messages. --}}
        @if (session('status'))
            <div class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-800">
                {{ session('status') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ session('error') }}
            </div>
        @endif

        {{-- Filter & search. --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="search" class="sr-only">Cari pengguna</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </span>
                        <input
                            type="search"
                            id="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Cari nama atau email..."
                            class="block w-full rounded-lg border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30"
                        >
                    </div>
                </div>
                <div>
                    <label for="role-filter" class="sr-only">Filter peran</label>
                    <select
                        id="role-filter"
                        wire:model.live="roleFilter"
                        class="block w-full rounded-lg border-slate-300 bg-white py-2.5 pl-3 pr-8 text-sm text-slate-900 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30"
                    >
                        <option value="">Semua peran</option>
                        <option value="{{ UserModel::ROLE_USER }}">User</option>
                        <option value="{{ UserModel::ROLE_ADMIN }}">Admin</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Tabel pengguna. --}}
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Pengguna
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Peran
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Terdaftar
                            </th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Aksi</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($this->users as $user)
                            <tr wire:key="user-{{ $user->id }}" class="transition hover:bg-slate-50/50">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700">
                                            {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-slate-900">
                                                {{ $user->name }}
                                                @if ($user->id === auth()->id())
                                                    <span class="ml-1 text-xs font-normal text-slate-500">(Anda)</span>
                                                @endif
                                            </p>
                                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $rolePillClasses[$user->role] ?? '' }}">
                                        {{ $roleLabels[$user->role] ?? $user->role }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                    {{ $user->created_at?->translatedFormat('d M Y') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <div class="inline-flex items-center gap-1">
                                        <button
                                            type="button"
                                            wire:click="openEdit({{ $user->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                                            </svg>
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="confirmDelete({{ $user->id }})"
                                            @disabled($user->id === auth()->id())
                                            class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:text-slate-400 disabled:hover:bg-transparent"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    @if ($search !== '' || $roleFilter !== '')
                                        <p class="text-sm font-medium text-slate-700">Tidak ada pengguna yang cocok</p>
                                        <p class="mt-1 text-xs text-slate-500">Sesuaikan kata kunci atau filter peran.</p>
                                        <button
                                            type="button"
                                            wire:click="$set('search', ''); $set('roleFilter', '')"
                                            class="mt-3 inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                                        >
                                            Bersihkan filter
                                        </button>
                                    @else
                                        <p class="text-sm font-medium text-slate-700">Belum ada pengguna</p>
                                        <p class="mt-1 text-xs text-slate-500">Klik "Tambah Pengguna" untuk membuat akun pertama.</p>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->users->hasPages())
                <div class="border-t border-slate-200 bg-slate-50 px-6 py-3">
                    {{ $this->users->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- ───────── Modal: Form Create / Edit ───────── --}}
    @if ($showFormModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4"
            role="dialog"
            aria-modal="true"
            wire:transition
        >
            <div
                class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm"
                wire:click="closeFormModal"
            ></div>

            <div class="relative w-full max-w-lg rounded-xl bg-white shadow-2xl ring-1 ring-slate-200">
                <form wire:submit="save">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-slate-900">
                            {{ $editingId ? 'Edit Pengguna' : 'Tambah Pengguna Baru' }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $editingId ? 'Ubah informasi pengguna. Kosongkan kata sandi bila tidak ingin diubah.' : 'Pengguna akan menerima akses sesuai peran yang dipilih.' }}
                        </p>
                    </div>

                    <div class="space-y-4 px-6 py-5">
                        <div>
                            <x-input-label for="form-name" value="Nama lengkap" />
                            <x-text-input wire:model="name" id="form-name" type="text" class="mt-1.5" placeholder="Nama pengguna" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                        </div>

                        <div>
                            <x-input-label for="form-email" value="Alamat email" />
                            <x-text-input wire:model="email" id="form-email" type="email" class="mt-1.5" placeholder="nama@email.com" required />
                            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                        </div>

                        <div>
                            <x-input-label for="form-role" value="Peran" />
                            <select
                                wire:model="role"
                                id="form-role"
                                class="mt-1.5 block w-full rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30"
                            >
                                <option value="{{ UserModel::ROLE_USER }}">User</option>
                                <option value="{{ UserModel::ROLE_ADMIN }}">Admin</option>
                            </select>
                            <x-input-error :messages="$errors->get('role')" class="mt-1.5" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="form-password" :value="$editingId ? 'Kata sandi baru (opsional)' : 'Kata sandi'" />
                                <x-text-input wire:model="password" id="form-password" type="password" class="mt-1.5" placeholder="Minimal 8 karakter" :required="! $editingId" autocomplete="new-password" />
                                <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="form-password-confirmation" value="Konfirmasi kata sandi" />
                                <x-text-input wire:model="password_confirmation" id="form-password-confirmation" type="password" class="mt-1.5" placeholder="Ulangi kata sandi" autocomplete="new-password" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
                        <x-secondary-button type="button" wire:click="closeFormModal">
                            Batal
                        </x-secondary-button>
                        <x-primary-button>
                            <span wire:loading.remove wire:target="save">
                                {{ $editingId ? 'Simpan Perubahan' : 'Buat Pengguna' }}
                            </span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ───────── Modal: Konfirmasi Hapus ───────── --}}
    @if ($showDeleteModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm"
                wire:click="closeDeleteModal"
            ></div>

            <div class="relative w-full max-w-md rounded-xl bg-white shadow-2xl ring-1 ring-slate-200">
                <div class="px-6 py-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-red-50 text-red-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base font-semibold text-slate-900">Hapus pengguna?</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                Anda akan menghapus <span class="font-semibold text-slate-900">{{ $deletingName }}</span>.
                                Tindakan ini tidak dapat dibatalkan dan semua data terkait akan ikut terhapus.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
                    <x-secondary-button type="button" wire:click="closeDeleteModal">Batal</x-secondary-button>
                    <x-danger-button type="button" wire:click="delete">
                        <span wire:loading.remove wire:target="delete">Ya, Hapus</span>
                        <span wire:loading wire:target="delete">Menghapus...</span>
                    </x-danger-button>
                </div>
            </div>
        </div>
    @endif
</div>
