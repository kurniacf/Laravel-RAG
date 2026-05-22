@php
    use App\Models\Document;

    $statusBadge = [
        Document::STATUS_PENDING    => 'bg-slate-100 text-slate-700 ring-slate-600/20',
        Document::STATUS_PROCESSING => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        Document::STATUS_READY      => 'bg-brand-50 text-brand-700 ring-brand-600/20',
        Document::STATUS_FAILED     => 'bg-red-50 text-red-700 ring-red-600/20',
    ];
@endphp

<div class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">

        {{-- Header. --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Materi Belajar</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                    Dokumen
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    Unggah PDF materi. Sistem akan otomatis mengekstrak teks agar
                    siap dianalisis di tahap berikutnya.
                </p>
            </div>
            <x-primary-button type="button" wire:click="openUpload">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Unggah Dokumen
            </x-primary-button>
        </header>

        {{-- Flash. --}}
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

        {{-- Filter. --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </span>
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Cari judul dokumen..."
                            class="block w-full rounded-lg border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30"
                        >
                    </div>
                </div>
                <div>
                    <select
                        wire:model.live="statusFilter"
                        class="block w-full rounded-lg border-slate-300 bg-white py-2.5 pl-3 pr-8 text-sm text-slate-900 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30"
                    >
                        <option value="">Semua status</option>
                        <option value="{{ Document::STATUS_PENDING }}">Menunggu</option>
                        <option value="{{ Document::STATUS_PROCESSING }}">Memproses</option>
                        <option value="{{ Document::STATUS_READY }}">Siap</option>
                        <option value="{{ Document::STATUS_FAILED }}">Gagal</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Tabel. --}}
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Dokumen</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Mata Pelajaran</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Ukuran</th>
                            <th class="relative px-6 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($this->documents as $document)
                            <tr wire:key="doc-{{ $document->id }}" class="transition hover:bg-slate-50/50">
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-0.5 inline-flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-slate-900">
                                                {{ $document->title }}
                                            </p>
                                            <p class="truncate text-xs text-slate-500">
                                                {{ $document->original_filename }}
                                                @if ($document->page_count)
                                                    <span class="mx-1.5 text-slate-300">·</span>
                                                    {{ $document->page_count }} hal
                                                @endif
                                                @if ($document->word_count)
                                                    <span class="mx-1.5 text-slate-300">·</span>
                                                    {{ number_format($document->word_count) }} kata
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    @if ($document->subject)
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium" style="background-color: {{ $document->subject->color_hex }}1a; color: {{ $document->subject->color_hex }};">
                                            {{ $document->subject->name }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $statusBadge[$document->status] ?? '' }}">
                                        {{ $document->statusLabel() }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600 tabular-nums">
                                    {{ number_format($document->file_size_bytes / 1024, 1) }} KB
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        @if ($document->isReady() && $document->total_chunks === 0)
                                            <button
                                                type="button"
                                                wire:click="indexToVector({{ $document->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="indexToVector({{ $document->id }})"
                                                class="inline-flex items-center gap-1.5 rounded-md bg-brand-50 px-2.5 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-100 disabled:cursor-not-allowed disabled:opacity-60"
                                                title="Proses chunking dan embedding ke pgvector"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                                                </svg>
                                                <span wire:loading.remove wire:target="indexToVector({{ $document->id }})">Proses ke Vector</span>
                                                <span wire:loading wire:target="indexToVector({{ $document->id }})">Memproses...</span>
                                            </button>
                                        @elseif ($document->total_chunks > 0)
                                            <span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-600" title="Sudah terindeks">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 text-brand-600">
                                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                                </svg>
                                                {{ $document->total_chunks }} chunks
                                            </span>
                                            <a
                                                href="{{ route('chat.index') }}"
                                                wire:navigate
                                                class="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-brand-700"
                                                title="Chat dengan dokumen ini"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                                </svg>
                                                Chat
                                            </a>
                                        @endif
                                        <button
                                            type="button"
                                            wire:click="openDetail({{ $document->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                            Detail
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="confirmDelete({{ $document->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50"
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
                                <td colspan="5" class="px-6 py-16 text-center">
                                    @if ($search !== '' || $statusFilter !== '')
                                        <p class="text-sm font-medium text-slate-700">Tidak ada dokumen yang cocok</p>
                                        <p class="mt-1 text-xs text-slate-500">Sesuaikan kata kunci atau filter status.</p>
                                        <button
                                            type="button"
                                            wire:click="$set('search', ''); $set('statusFilter', '')"
                                            class="mt-3 inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                                        >
                                            Bersihkan filter
                                        </button>
                                    @else
                                        <p class="text-sm font-medium text-slate-700">Belum ada dokumen</p>
                                        <p class="mt-1 text-xs text-slate-500">Klik "Unggah Dokumen" untuk mulai mengisi koleksi materi.</p>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->documents->hasPages())
                <div class="border-t border-slate-200 bg-slate-50 px-6 py-3">
                    {{ $this->documents->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- ───────── Modal Upload ───────── --}}
    @if ($showUploadModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4"
            role="dialog"
            aria-modal="true"
            x-data="{
                /** 'idle' | 'uploading' | 'uploaded' | 'processing' | 'done' | 'error' */
                stage: 'idle',
                dragging: false,
                fileName: '',
                fileSize: 0,
                progress: 0,
                errorMsg: '',

                /* ─── Pemilihan file ─── */

                onDrop(e) {
                    this.dragging = false;
                    const f = e.dataTransfer.files[0];
                    if (! f) return;
                    this.injectFile(f);
                },

                onPick(e) {
                    const f = e.target.files[0];
                    if (! f) return;
                    this.setMeta(f);
                    // Tidak perlu inject; Livewire wire:model akan handle.
                },

                /**
                 * Tempatkan file dari drag-drop ke <input>, lalu trigger
                 * native 'change' event. Mekanisme ini memastikan Livewire
                 * wire:model bekerja seperti saat user klik-pilih biasa,
                 * tanpa perlu memanggil `$wire.upload` manual.
                 */
                injectFile(f) {
                    if (f.type !== 'application/pdf' && ! f.name.toLowerCase().endsWith('.pdf')) {
                        alert('Hanya berkas PDF yang diterima.');
                        return;
                    }
                    this.setMeta(f);
                    const dt = new DataTransfer();
                    dt.items.add(f);
                    this.$refs.input.files = dt.files;
                    this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
                },

                setMeta(f) {
                    this.fileName = f.name;
                    this.fileSize = f.size;
                    this.errorMsg = '';
                },

                clearFile() {
                    this.fileName = '';
                    this.fileSize = 0;
                    this.stage = 'idle';
                    this.progress = 0;
                    this.errorMsg = '';
                    if (this.$refs.input) this.$refs.input.value = '';
                    // Sinkronkan property server: kosongkan supaya validasi tahu file belum ada.
                    $wire.set('file', null, false);
                },

                formatSize(b) {
                    if (! b) return '';
                    return b < 1024 * 1024
                        ? (b / 1024).toFixed(1) + ' KB'
                        : (b / 1024 / 1024).toFixed(2) + ' MB';
                },

                onSubmit() {
                    if (this.stage !== 'uploaded') return;
                    this.stage = 'processing';
                },
            }"
            x-on:livewire-upload-start.window="stage = 'uploading'; progress = 0; errorMsg = ''"
            x-on:livewire-upload-progress.window="progress = $event.detail.progress"
            x-on:livewire-upload-finish.window="stage = 'uploaded'; progress = 100"
            x-on:livewire-upload-error.window="stage = 'error'; errorMsg = 'Gagal mengunggah berkas. Coba lagi.'"
            x-on:document-uploaded.window="stage = 'done'"
        >
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" wire:click="closeUpload"></div>

            <div class="relative w-full max-w-lg rounded-xl bg-white shadow-2xl ring-1 ring-slate-200">
                <form wire:submit="submitUpload" @submit="onSubmit()">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-slate-900">Unggah Dokumen</h3>
                        <p class="mt-1 text-xs text-slate-500">
                            PDF berbasis teks, maksimal {{ number_format(Document::MAX_FILE_SIZE / 1024 / 1024, 0) }} MB
                            dan {{ Document::MAX_PAGES }} halaman.
                        </p>
                    </div>

                    <div class="space-y-4 px-6 py-5">

                        {{-- ─── Stepper progres (muncul saat ada aktivitas) ─── --}}
                        <div
                            x-show="stage !== 'idle'"
                            x-cloak
                            x-transition.opacity
                            class="rounded-xl border border-slate-200 bg-slate-50/70 p-4"
                        >
                            <ol class="flex items-center justify-between gap-2">
                                @php
                                    // Definisi langkah stepper. Tiap langkah punya 3 status visual:
                                    //  active (sedang berjalan), done (sudah lewat), pending (belum).
                                    $steps = [
                                        ['key' => 'upload',  'label' => 'Mengunggah'],
                                        ['key' => 'extract', 'label' => 'Mengekstrak'],
                                        ['key' => 'done',    'label' => 'Selesai'],
                                    ];
                                @endphp

                                @foreach ($steps as $i => $step)
                                    @php
                                        // Mapping: stage Alpine ↔ index aktif (0/1/2).
                                        // - upload:  uploading
                                        // - extract: processing
                                        // - done:    done
                                        $jsActiveExpr = match ($step['key']) {
                                            'upload'  => "stage === 'uploading'",
                                            'extract' => "stage === 'processing'",
                                            'done'    => "stage === 'done'",
                                        };
                                        $jsDoneExpr = match ($step['key']) {
                                            'upload'  => "['uploaded','processing','done'].includes(stage)",
                                            'extract' => "stage === 'done'",
                                            'done'    => 'false',
                                        };
                                    @endphp

                                    <li class="flex flex-1 items-center gap-2">
                                        {{-- Bulatan langkah --}}
                                        <span
                                            :class="
                                                ({{ $jsDoneExpr }}) ? 'bg-brand-600 text-white ring-brand-600' :
                                                ({{ $jsActiveExpr }}) ? 'bg-brand-50 text-brand-700 ring-brand-500' :
                                                'bg-white text-slate-400 ring-slate-300'
                                            "
                                            class="inline-flex h-7 w-7 flex-none items-center justify-center rounded-full text-xs font-semibold ring-2 ring-inset transition"
                                        >
                                            {{-- Spinner saat aktif --}}
                                            <template x-if="{{ $jsActiveExpr }}">
                                                <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4l3-3-3-3v4a8 8 0 1 0 8 8h-4l3 3 3-3h-4a8 8 0 0 1-8 8z"></path>
                                                </svg>
                                            </template>

                                            {{-- Check saat sudah lewat --}}
                                            <template x-if="{{ $jsDoneExpr }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                                </svg>
                                            </template>

                                            {{-- Nomor saat pending --}}
                                            <template x-if="! ({{ $jsActiveExpr }}) && ! ({{ $jsDoneExpr }})">
                                                <span>{{ $i + 1 }}</span>
                                            </template>
                                        </span>

                                        <span
                                            :class="
                                                ({{ $jsActiveExpr }}) ? 'text-slate-900 font-semibold' :
                                                ({{ $jsDoneExpr }}) ? 'text-slate-700' :
                                                'text-slate-400'
                                            "
                                            class="truncate text-xs transition"
                                        >
                                            {{ $step['label'] }}
                                        </span>

                                        {{-- Garis penghubung antar langkah --}}
                                        @if ($i < count($steps) - 1)
                                            <span
                                                :class="({{ $jsDoneExpr }}) ? 'bg-brand-500' : 'bg-slate-200'"
                                                class="ms-1 hidden h-px flex-1 transition sm:inline-block"
                                            ></span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>

                            {{-- Detail status di bawah stepper. --}}
                            <div class="mt-3 text-xs text-slate-600">
                                <p x-show="stage === 'uploading'" x-cloak>
                                    <span x-text="`Mengunggah berkas ke server... ${progress}%`"></span>
                                </p>
                                <p x-show="stage === 'uploaded'" x-cloak>
                                    Berkas siap diproses. Klik "Unggah & Proses" untuk melanjutkan.
                                </p>
                                <p x-show="stage === 'processing'" x-cloak>
                                    Mengekstrak teks dari PDF dan menyimpan dokumen. Tahap ini berjalan sinkron, mohon tunggu.
                                </p>
                                <p x-show="stage === 'done'" x-cloak class="font-medium text-brand-700">
                                    Selesai! Dokumen tersimpan dan siap dilihat.
                                </p>
                                <p x-show="stage === 'error'" x-cloak class="font-medium text-red-700" x-text="errorMsg"></p>
                            </div>

                            {{-- Bar progres tipis untuk tahap unggah. --}}
                            <div x-show="stage === 'uploading'" x-cloak class="mt-2 h-1 w-full overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full bg-brand-500 transition-all" :style="`width: ${progress}%`"></div>
                            </div>
                        </div>

                        {{-- ─── Form fields ─── --}}
                        <div>
                            <x-input-label for="upload-title" value="Judul dokumen" />
                            <x-text-input wire:model="title" id="upload-title" type="text" class="mt-1.5" placeholder="Mis. Pengantar Kalkulus Bab 1" required />
                            <x-input-error :messages="$errors->get('title')" class="mt-1.5" />
                        </div>

                        <div>
                            <x-input-label for="upload-subject" value="Mata pelajaran (opsional)" />
                            <select
                                wire:model="subject_id"
                                id="upload-subject"
                                class="mt-1.5 block w-full rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30"
                            >
                                <option value="">Tanpa mata pelajaran</option>
                                @foreach ($this->subjectOptions as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('subject_id')" class="mt-1.5" />
                        </div>

                        {{-- ─── Dropzone (drag-drop + klik) ─── --}}
                        <div>
                            <x-input-label value="Berkas PDF" />

                            <label
                                for="upload-file"
                                @dragover.prevent="dragging = true"
                                @dragenter.prevent="dragging = true"
                                @dragleave.prevent="dragging = false"
                                @drop.prevent="onDrop($event)"
                                :class="dragging
                                    ? 'border-brand-500 bg-brand-50/60 ring-2 ring-brand-500/20'
                                    : (fileName ? 'border-brand-300 bg-brand-50/30' : 'border-slate-300 bg-slate-50/50 hover:bg-slate-100/60')"
                                class="mt-1.5 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-8 text-center transition"
                            >
                                {{--
                                    Input file pakai wire:model="file" — Livewire akan
                                    otomatis upload ke temp storage saat 'change' event
                                    di-trigger (oleh user atau oleh onDrop kita). Kita
                                    TIDAK memanggil $wire.upload() manual karena rentan
                                    bug "Cannot read properties of undefined".
                                --}}
                                <input
                                    wire:model="file"
                                    x-ref="input"
                                    id="upload-file"
                                    type="file"
                                    accept="application/pdf,.pdf"
                                    @change="onPick($event)"
                                    class="sr-only"
                                >

                                <template x-if="! fileName">
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-brand-50 text-brand-700 transition" :class="dragging && 'scale-110 bg-brand-100'">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                            </svg>
                                        </span>
                                        <div>
                                            <p class="text-sm font-medium text-slate-800">
                                                <span x-show="! dragging">Seret berkas PDF ke sini</span>
                                                <span x-show="dragging" x-cloak class="text-brand-700">Lepas berkas untuk mengunggah</span>
                                            </p>
                                            <p class="mt-0.5 text-xs text-slate-500">
                                                atau <span class="font-semibold text-brand-700">klik untuk pilih</span> dari komputer
                                            </p>
                                        </div>
                                        <p class="text-[10px] uppercase tracking-wider text-slate-400">
                                            Maks {{ number_format(Document::MAX_FILE_SIZE / 1024 / 1024, 0) }} MB · {{ Document::MAX_PAGES }} halaman
                                        </p>
                                    </div>
                                </template>

                                <template x-if="fileName">
                                    <div class="flex w-full items-center gap-3" @click.stop>
                                        <span class="inline-flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-brand-100 text-brand-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                        </span>
                                        <div class="min-w-0 flex-1 text-left">
                                            <p class="truncate text-sm font-medium text-slate-900" x-text="fileName"></p>
                                            <p class="text-xs text-slate-500" x-text="formatSize(fileSize)"></p>
                                        </div>
                                        <button
                                            type="button"
                                            @click.prevent="clearFile()"
                                            :disabled="stage === 'processing'"
                                            class="inline-flex h-7 w-7 flex-none items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                                            title="Ganti berkas"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                            </label>

                            <x-input-error :messages="$errors->get('file')" class="mt-1.5" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
                        <x-secondary-button
                            type="button"
                            wire:click="closeUpload"
                            x-bind:disabled="stage === 'processing' || stage === 'uploading'"
                        >
                            Batal
                        </x-secondary-button>
                        <x-primary-button
                            x-bind:disabled="stage !== 'uploaded' && stage !== 'error'"
                        >
                            <span x-show="stage !== 'processing'">Unggah & Proses</span>
                            <span x-show="stage === 'processing'" x-cloak class="inline-flex items-center gap-2">
                                <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4l3-3-3-3v4a8 8 0 1 0 8 8h-4l3 3 3-3h-4a8 8 0 0 1-8 8z"></path>
                                </svg>
                                Memproses...
                            </span>
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ───────── Modal Detail ───────── --}}
    @if ($showDetailModal && $this->detailDocument)
        @php $d = $this->detailDocument; @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" wire:click="closeDetail"></div>
            <div class="relative w-full max-w-3xl rounded-xl bg-white shadow-2xl ring-1 ring-slate-200">
                <div class="flex items-start justify-between border-b border-slate-200 px-6 py-4">
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-slate-900">{{ $d->title }}</h3>
                        <p class="mt-1 truncate text-xs text-slate-500">{{ $d->original_filename }}</p>
                    </div>
                    <button type="button" wire:click="closeDetail" class="ml-3 inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100" aria-label="Tutup">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5">
                    <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Status</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $statusBadge[$d->status] ?? '' }}">
                                    {{ $d->statusLabel() }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Halaman</dt>
                            <dd class="mt-1 text-sm text-slate-900 tabular-nums">{{ $d->page_count ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Kata</dt>
                            <dd class="mt-1 text-sm text-slate-900 tabular-nums">{{ $d->word_count ? number_format($d->word_count) : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Ukuran</dt>
                            <dd class="mt-1 text-sm text-slate-900 tabular-nums">{{ number_format($d->file_size_bytes / 1024, 1) }} KB</dd>
                        </div>
                    </dl>

                    @if ($d->subject)
                        <div class="mt-4">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Mata Pelajaran</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" style="background-color: {{ $d->subject->color_hex }}1a; color: {{ $d->subject->color_hex }};">
                                    {{ $d->subject->name }}
                                </span>
                            </dd>
                        </div>
                    @endif

                    @if ($d->error_message)
                        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3">
                            <p class="text-xs font-semibold text-red-800">Pesan kesalahan:</p>
                            <p class="mt-1 text-sm text-red-700">{{ $d->error_message }}</p>
                        </div>
                    @endif

                    @if ($d->extracted_text)
                        <div class="mt-5">
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Preview teks hasil ekstraksi</p>
                            <div class="mt-2 max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs leading-relaxed text-slate-700">
                                {{ Str::limit($d->extracted_text, 2000, '... [dipotong]') }}
                            </div>
                            <p class="mt-1 text-[10px] text-slate-400">Menampilkan 2.000 karakter pertama untuk preview.</p>
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
                    <x-secondary-button type="button" wire:click="closeDetail">Tutup</x-secondary-button>
                </div>
            </div>
        </div>
    @endif

    {{-- ───────── Modal Konfirmasi Hapus ───────── --}}
    @if ($showDeleteModal)
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
                            <h3 class="text-base font-semibold text-slate-900">Hapus dokumen?</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                Anda akan menghapus <span class="font-semibold text-slate-900">{{ $deletingTitle }}</span>.
                                File fisik beserta data hasil ekstraksi akan ikut hilang.
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
