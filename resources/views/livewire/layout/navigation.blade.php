<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Logout user dari aplikasi lalu redirect ke beranda.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $user = auth()->user();

    // Daftar menu sidebar. Tiap item bisa dibatasi oleh visible(callable):
    // mengembalikan boolean menentukan apakah link ditampilkan untuk user
    // yang sedang login.
    $sections = [
        [
            'label' => 'Belajar',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'dashboard',
                    'icon'  => 'home',
                    'visible' => fn ($u) => true,
                ],
                [
                    'label' => 'Mata Pelajaran',
                    'route' => 'subjects.index',
                    'icon'  => 'book',
                    'visible' => fn ($u) => true,
                ],
                [
                    'label' => 'Dokumen',
                    'route' => 'documents.index',
                    'icon'  => 'document',
                    'visible' => fn ($u) => true,
                ],
                [
                    'label' => 'Chat',
                    'route' => 'chat.index',
                    'icon'  => 'chat',
                    'visible' => fn ($u) => true,
                ],
            ],
        ],
        [
            'label' => 'Administrasi',
            'items' => [
                [
                    'label' => 'Manajemen Pengguna',
                    'route' => 'users.index',
                    'icon'  => 'users',
                    'visible' => fn ($u) => $u->isAdmin(),
                ],
            ],
        ],
    ];
@endphp

<aside
    class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    aria-label="Navigasi utama"
>
    {{-- Header sidebar: logo + tombol tutup di mobile. --}}
    <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
            <x-application-logo class="h-8 w-8 text-brand-600" />
            <span class="text-sm font-semibold tracking-tight text-slate-900">
                PintarBelajar AI
            </span>
        </a>
        <button
            @click="sidebarOpen = false"
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 lg:hidden"
            aria-label="Tutup menu navigasi"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Daftar menu. --}}
    <nav class="flex-1 overflow-y-auto px-3 py-4">
        @foreach ($sections as $section)
            @php
                $visibleItems = array_filter($section['items'], fn ($i) => ($i['visible'])($user));
            @endphp
            @if (count($visibleItems) > 0)
                <div class="mb-6">
                    <h3 class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        {{ $section['label'] }}
                    </h3>
                    <ul class="mt-2 space-y-0.5">
                        @foreach ($visibleItems as $item)
                            @php
                                $hasRoute = \Illuminate\Support\Facades\Route::has($item['route']);
                                $isActive = $hasRoute && request()->routeIs($item['route']);
                            @endphp
                            <li>
                                @if ($hasRoute)
                                    <a
                                        href="{{ route($item['route']) }}"
                                        wire:navigate
                                        @class([
                                            'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                                            'bg-brand-50 text-brand-700' => $isActive,
                                            'text-slate-700 hover:bg-slate-100 hover:text-slate-900' => ! $isActive,
                                        ])
                                    >
                                        @include('partials.sidebar-icon', ['name' => $item['icon'], 'active' => $isActive])
                                        <span>{{ $item['label'] }}</span>
                                    </a>
                                @else
                                    {{-- Route belum tersedia (fase belum dibangun): tampilkan sebagai placeholder. --}}
                                    <span
                                        class="group flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-400"
                                        title="Akan tersedia di fase berikutnya"
                                    >
                                        @include('partials.sidebar-icon', ['name' => $item['icon'], 'active' => false])
                                        <span>{{ $item['label'] }}</span>
                                        <span class="ml-auto text-[10px] uppercase tracking-wide text-slate-400">
                                            Soon
                                        </span>
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    </nav>

    {{-- Footer sidebar: identitas user + tombol logout. --}}
    <div class="border-t border-slate-200 p-3">
        <div class="flex items-center gap-3 px-2 py-2">
            <div class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700">
                {{ strtoupper(mb_substr($user->name, 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-slate-900">{{ $user->name }}</p>
                <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
            </div>
        </div>

        <div class="mt-2 grid grid-cols-2 gap-1">
            <a
                href="{{ route('profile') }}"
                wire:navigate
                class="inline-flex items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-3.5 w-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                Profil
            </a>
            <button
                wire:click="logout"
                type="button"
                class="inline-flex items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-red-50 hover:text-red-700"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-3.5 w-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                </svg>
                Keluar
            </button>
        </div>
    </div>
</aside>
