@php
    use App\Models\Subject;
@endphp

<div class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">

        {{-- Header. --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Master Data</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                    Mata Pelajaran
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    @if ($this->canManage())
                        Kelompokkan dokumen ke kategori belajar agar lebih mudah dicari.
                    @else
                        Daftar mata pelajaran yang tersedia. Hanya pengajar dan admin yang dapat mengelola.
                    @endif
                </p>
            </div>
            @if ($this->canManage())
                <x-primary-button type="button" wire:click="openCreate">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Tambah Mata Pelajaran
                </x-primary-button>
            @endif
        </header>

        {{-- Flash. --}}
        @if (session('status'))
            <div class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-800">
                {{ session('status') }}
            </div>
        @endif

        {{-- Search bar. --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <label for="subject-search" class="sr-only">Cari mata pelajaran</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </span>
                <input
                    type="search"
                    id="subject-search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari nama mata pelajaran..."
                    class="block w-full rounded-lg border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30"
                >
            </div>
        </div>

        {{-- Grid kartu mata pelajaran. --}}
        @if ($this->subjects->isEmpty())
            <div class="rounded-xl border border-dashed border-slate-300 bg-white py-16 text-center">
                @if ($search !== '')
                    {{-- Empty karena pencarian tidak ketemu. --}}
                    <p class="text-sm font-medium text-slate-700">Tidak ada hasil untuk "{{ $search }}"</p>
                    <p class="mt-1 text-xs text-slate-500">Coba kata kunci lain atau bersihkan pencarian.</p>
                    <button
                        type="button"
                        wire:click="$set('search', '')"
                        class="mt-3 inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Bersihkan pencarian
                    </button>
                @else
                    {{-- Empty karena belum ada data. --}}
                    <p class="text-sm font-medium text-slate-700">Belum ada mata pelajaran</p>
                    <p class="mt-1 text-xs text-slate-500">
                        @if ($this->canManage())
                            Klik "Tambah Mata Pelajaran" untuk membuat yang pertama.
                        @else
                            Tunggu admin menambah mata pelajaran.
                        @endif
                    </p>
                @endif
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->subjects as $subject)
                    <article
                        wire:key="subject-{{ $subject->id }}"
                        class="group overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span
                                    class="inline-flex h-10 w-10 flex-none items-center justify-center rounded-lg"
                                    style="background-color: {{ $subject->color_hex }}1a; color: {{ $subject->color_hex }};"
                                >
                                    @include('partials.subject-icon', ['name' => $subject->icon ?? 'book', 'class' => 'h-5 w-5'])
                                </span>
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-semibold text-slate-900">
                                        {{ $subject->name }}
                                    </h3>
                                    <p class="truncate text-xs text-slate-500">
                                        /{{ $subject->slug }}
                                    </p>
                                </div>
                            </div>
                            @if ($this->canManage())
                                <div class="flex flex-none items-center gap-1 opacity-0 transition group-hover:opacity-100">
                                    <button
                                        type="button"
                                        wire:click="openEdit({{ $subject->id }})"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                                        title="Edit"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="confirmDelete({{ $subject->id }})"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md text-red-600 transition hover:bg-red-50"
                                        title="Hapus"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            @endif
                        </div>

                        <div class="mt-4 flex items-center justify-between text-xs">
                            <span class="inline-flex items-center gap-1 text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-3.5 w-3.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                {{ $subject->documents_count }} dokumen
                            </span>
                            <span class="text-slate-400">
                                {{ $subject->created_at?->translatedFormat('d M Y') }}
                            </span>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($this->subjects->hasPages())
                <div class="mt-2">
                    {{ $this->subjects->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- Modal Create/Edit (hanya untuk yang berhak). --}}
    @if ($showFormModal && $this->canManage())
        <div
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4"
            role="dialog"
            aria-modal="true"
        >
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" wire:click="closeFormModal"></div>

            <div class="relative w-full max-w-lg rounded-xl bg-white shadow-2xl ring-1 ring-slate-200">
                <form wire:submit="save">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-slate-900">
                            {{ $editingId ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran' }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Slug otomatis dibuat dari nama dan akan menjadi pengenal URL.
                        </p>
                    </div>

                    <div class="space-y-4 px-6 py-5">
                        <div>
                            <x-input-label for="subject-name" value="Nama" />
                            <x-text-input wire:model="name" id="subject-name" type="text" class="mt-1.5" placeholder="Mis. Matematika Dasar" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                        </div>

                        <div>
                            <x-input-label value="Ikon" />
                            <div class="mt-1.5 grid grid-cols-8 gap-2">
                                @foreach (Subject::ICONS as $iconOption)
                                    <button
                                        type="button"
                                        wire:click="$set('icon', '{{ $iconOption }}')"
                                        @class([
                                            'inline-flex h-10 w-10 items-center justify-center rounded-lg border transition',
                                            'border-brand-600 bg-brand-50 text-brand-700 ring-2 ring-brand-600/30' => $icon === $iconOption,
                                            'border-slate-200 text-slate-500 hover:border-slate-300 hover:bg-slate-50' => $icon !== $iconOption,
                                        ])
                                        title="{{ $iconOption }}"
                                    >
                                        @include('partials.subject-icon', ['name' => $iconOption, 'class' => 'h-5 w-5'])
                                    </button>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('icon')" class="mt-1.5" />
                        </div>

                        <div>
                            <x-input-label value="Warna" />
                            <div class="mt-1.5 flex flex-wrap gap-2">
                                @foreach (Subject::COLORS as $colorOption)
                                    <button
                                        type="button"
                                        wire:click="$set('color_hex', '{{ $colorOption }}')"
                                        @class([
                                            'h-9 w-9 rounded-lg ring-2 ring-offset-2 transition',
                                            'ring-slate-900/40' => $color_hex === $colorOption,
                                            'ring-transparent hover:ring-slate-300' => $color_hex !== $colorOption,
                                        ])
                                        style="background-color: {{ $colorOption }};"
                                        title="{{ $colorOption }}"
                                    ></button>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('color_hex')" class="mt-1.5" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
                        <x-secondary-button type="button" wire:click="closeFormModal">Batal</x-secondary-button>
                        <x-primary-button>
                            <span wire:loading.remove wire:target="save">
                                {{ $editingId ? 'Simpan Perubahan' : 'Buat' }}
                            </span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal Konfirmasi Hapus. --}}
    @if ($showDeleteModal && $this->canManage())
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" wire:click="closeDeleteModal"></div>
            <div class="relative w-full max-w-md rounded-xl bg-white shadow-2xl ring-1 ring-slate-200">
                <div class="px-6 py-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-red-50 text-red-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Hapus mata pelajaran?</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                Anda akan menghapus <span class="font-semibold text-slate-900">{{ $deletingName }}</span>.
                                Dokumen yang terkait dengan mata pelajaran ini tidak akan ikut terhapus,
                                tapi referensinya akan menjadi kosong.
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
