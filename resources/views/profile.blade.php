<x-app-layout>
    <x-slot name="title">Profil</x-slot>

    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl space-y-6">

            {{-- Header halaman. --}}
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Akun</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Profil Saya</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Kelola informasi akun, kata sandi, dan keamanan dari satu tempat.
                </p>
            </div>

            {{-- Kartu identitas: avatar inisial + nama + email + peran. --}}
            @php $u = auth()->user(); @endphp
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col items-center gap-4 p-6 text-center sm:flex-row sm:text-left">
                    <span class="inline-flex h-20 w-20 flex-none items-center justify-center rounded-full bg-brand-600 text-2xl font-semibold text-white">
                        {{ strtoupper(mb_substr($u->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                            <h2 class="text-lg font-semibold text-slate-900">{{ $u->name }}</h2>
                            <span @class([
                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
                                'bg-brand-50 text-brand-700 ring-brand-600/20' => $u->isAdmin(),
                                'bg-slate-100 text-slate-700 ring-slate-600/20' => $u->isUser(),
                            ])>
                                {{ $u->roleLabel() }}
                            </span>
                        </div>
                        <p class="mt-0.5 truncate text-sm text-slate-500">{{ $u->email }}</p>
                    </div>
                </div>
            </div>

            {{-- Section 1 — Informasi Profil. --}}
            <livewire:profile.update-profile-information-form />

            {{-- Section 2 — Ubah Kata Sandi. --}}
            <livewire:profile.update-password-form />

            {{-- Section 3 — Informasi Akun (read-only). --}}
            <livewire:profile.account-info />

            {{-- Section 4 — Zona Berbahaya. --}}
            <livewire:profile.delete-user-form />

        </div>
    </div>
</x-app-layout>
