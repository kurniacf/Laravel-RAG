@php
    use App\Models\AiJob;

    $statusBadge = [
        AiJob::STATUS_PENDING   => 'bg-slate-100 text-slate-700 ring-slate-600/20',
        AiJob::STATUS_RUNNING   => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        AiJob::STATUS_COMPLETED => 'bg-brand-50 text-brand-700 ring-brand-600/20',
        AiJob::STATUS_FAILED    => 'bg-red-50 text-red-700 ring-red-600/20',
    ];

    // Warna badge per jenis job — bantu membedakan kategori sekilas pandang.
    $typeBadge = [
        AiJob::TYPE_PARSE         => 'bg-slate-50 text-slate-700 ring-slate-500/20',
        AiJob::TYPE_CHUNK         => 'bg-indigo-50 text-indigo-700 ring-indigo-500/20',
        AiJob::TYPE_EMBED         => 'bg-violet-50 text-violet-700 ring-violet-500/20',
        AiJob::TYPE_SUMMARIZE     => 'bg-sky-50 text-sky-700 ring-sky-500/20',
        AiJob::TYPE_QUIZ_GEN      => 'bg-emerald-50 text-emerald-700 ring-emerald-500/20',
        AiJob::TYPE_FLASHCARD_GEN => 'bg-amber-50 text-amber-700 ring-amber-500/20',
    ];

    $isAdmin = auth()->user()->isAdmin();
    $hasFilters = $search !== '' || $statusFilter !== '' || $typeFilter !== '' || $userFilter !== '';
@endphp

<div class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">

        {{-- Header --}}
        <header>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Audit Sistem</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                Riwayat Aktivitas
            </h1>
            <p class="mt-1 text-sm text-slate-600">
                Catatan pekerjaan AI yang berjalan di sistem: ekstraksi PDF, indexing vector,
                ringkasan, kuis, dan flashcard. Memberi transparansi proses, durasi, dan
                pemakaian token.
            </p>
        </header>

        {{-- Statistik 4 kartu --}}
        @php($stats = $this->stats)
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Total Aktivitas</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">
                    {{ number_format($stats['total']) }}
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Selesai</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-brand-700">
                    {{ number_format($stats['completed']) }}
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Gagal</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums {{ $stats['failed'] > 0 ? 'text-red-700' : 'text-slate-900' }}">
                    {{ number_format($stats['failed']) }}
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Token Terpakai</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">
                    {{ number_format($stats['tokens']) }}
                </p>
            </div>
        </div>

        {{-- Filter --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div class="{{ $isAdmin ? '' : 'sm:col-span-2' }}">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </span>
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Cari nama dokumen atau file..."
                            class="block w-full rounded-lg border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30"
                        >
                    </div>
                </div>
                <div>
                    <select
                        wire:model.live="typeFilter"
                        class="block w-full rounded-lg border-slate-300 bg-white py-2.5 pl-3 pr-8 text-sm text-slate-900 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30"
                    >
                        <option value="">Semua jenis</option>
                        @foreach (AiJob::TYPES as $type)
                            <option value="{{ $type }}">{{ AiJob::typeLabelFor($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <select
                        wire:model.live="statusFilter"
                        class="block w-full rounded-lg border-slate-300 bg-white py-2.5 pl-3 pr-8 text-sm text-slate-900 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30"
                    >
                        <option value="">Semua status</option>
                        @foreach (AiJob::STATUSES as $status)
                            <option value="{{ $status }}">{{ AiJob::statusLabelFor($status) }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($isAdmin)
                    <div>
                        <select
                            wire:model.live="userFilter"
                            class="block w-full rounded-lg border-slate-300 bg-white py-2.5 pl-3 pr-8 text-sm text-slate-900 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30"
                        >
                            <option value="">Semua pengguna</option>
                            @foreach ($this->userOptions as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </div>

        {{-- Tabel --}}
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Pekerjaan</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Dokumen</th>
                            @if ($isAdmin)
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Pemilik</th>
                            @endif
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Durasi</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Token</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Waktu</th>
                            <th class="relative px-6 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($this->jobs as $job)
                            <tr
                                wire:key="job-{{ $job->id }}"
                                wire:click="openDetail({{ $job->id }})"
                                class="cursor-pointer transition hover:bg-slate-50/60"
                            >
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $typeBadge[$job->job_type] ?? 'bg-slate-100 text-slate-700 ring-slate-500/20' }}">
                                        {{ $job->typeLabel() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if ($job->document)
                                        <p class="truncate text-sm font-medium text-slate-900">{{ $job->document->title }}</p>
                                        @if ($job->document->subject)
                                            <p class="mt-0.5 text-xs text-slate-500">
                                                <span class="inline-flex items-center gap-1.5">
                                                    <span class="inline-block h-1.5 w-1.5 rounded-full" style="background-color: {{ $job->document->subject->color_hex }};"></span>
                                                    {{ $job->document->subject->name }}
                                                </span>
                                            </p>
                                        @endif
                                    @else
                                        <span class="text-xs italic text-slate-400">(dokumen telah dihapus)</span>
                                    @endif
                                </td>
                                @if ($isAdmin)
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">
                                        {{ $job->document?->user?->name ?? '—' }}
                                    </td>
                                @endif
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $statusBadge[$job->status] ?? '' }}">
                                        @if ($job->status === AiJob::STATUS_RUNNING)
                                            <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4l3-3-3-3v4a8 8 0 1 0 8 8h-4l3 3 3-3h-4a8 8 0 0 1-8 8z"></path>
                                            </svg>
                                        @endif
                                        {{ $job->statusLabel() }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm tabular-nums text-slate-700">
                                    {{ $job->durationLabel() ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm tabular-nums text-slate-700">
                                    {{ $job->tokens_used ? number_format($job->tokens_used) : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500" title="{{ $job->created_at?->format('d M Y H:i:s') }}">
                                    {{ $job->created_at?->diffForHumans() }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <span class="inline-flex items-center text-xs font-medium text-brand-700">
                                        Detail
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="ms-1 h-3.5 w-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                        </svg>
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdmin ? 8 : 7 }}" class="px-6 py-16 text-center">
                                    @if ($hasFilters)
                                        <p class="text-sm font-medium text-slate-700">Tidak ada aktivitas yang cocok</p>
                                        <p class="mt-1 text-xs text-slate-500">Sesuaikan kata kunci atau filter.</p>
                                        <button
                                            type="button"
                                            wire:click="clearFilters"
                                            class="mt-3 inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                                        >
                                            Bersihkan filter
                                        </button>
                                    @else
                                        <p class="text-sm font-medium text-slate-700">Belum ada aktivitas</p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            Setelah Anda mengunggah dokumen, memproses ke vector, atau
                                            membuat ringkasan/kuis/flashcard, jejaknya akan muncul di sini.
                                        </p>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->jobs->hasPages())
                <div class="border-t border-slate-200 bg-slate-50 px-6 py-3">
                    {{ $this->jobs->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Modal detail --}}
    @if ($this->detail)
        @php($job = $this->detail)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" wire:click="closeDetail"></div>
            <div class="relative w-full max-w-xl rounded-xl bg-white shadow-2xl ring-1 ring-slate-200">
                <div class="flex items-start justify-between border-b border-slate-200 px-6 py-4">
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-slate-900">{{ $job->typeLabel() }}</h3>
                        <p class="mt-0.5 truncate text-xs text-slate-500">
                            ID #{{ $job->id }} · {{ $job->created_at?->format('d M Y H:i:s') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeDetail"
                        class="ms-3 inline-flex h-8 w-8 flex-none items-center justify-center rounded-md text-slate-500 hover:bg-slate-100"
                        aria-label="Tutup detail"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4 px-6 py-5 text-sm">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Status</p>
                            <p class="mt-1">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $statusBadge[$job->status] ?? '' }}">
                                    {{ $job->statusLabel() }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Durasi</p>
                            <p class="mt-1 font-medium tabular-nums text-slate-900">{{ $job->durationLabel() ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Token Terpakai</p>
                            <p class="mt-1 font-medium tabular-nums text-slate-900">
                                {{ $job->tokens_used ? number_format($job->tokens_used) : '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Pemilik</p>
                            <p class="mt-1 font-medium text-slate-900">{{ $job->document?->user?->name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Mulai</p>
                            <p class="mt-1 text-slate-700">{{ $job->started_at?->format('d M Y H:i:s') ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Selesai</p>
                            <p class="mt-1 text-slate-700">{{ $job->finished_at?->format('d M Y H:i:s') ?? '—' }}</p>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Dokumen</p>
                        @if ($job->document)
                            <a
                                href="{{ route('documents.show', $job->document) }}"
                                wire:navigate
                                class="mt-1 inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800"
                            >
                                {{ $job->document->title }}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $job->document->original_filename }}</p>
                        @else
                            <p class="mt-1 text-sm italic text-slate-500">(dokumen telah dihapus)</p>
                        @endif
                    </div>

                    @if ($job->error_message)
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-red-700">Pesan Error</p>
                            <pre class="mt-1 whitespace-pre-wrap rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-900">{{ $job->error_message }}</pre>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <x-secondary-button type="button" wire:click="closeDetail">Tutup</x-secondary-button>
                </div>
            </div>
        </div>
    @endif
</div>
