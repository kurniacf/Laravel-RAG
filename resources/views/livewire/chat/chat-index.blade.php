<div class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl space-y-6">

        {{-- Header. --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tanya AI</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                    Chat dengan Dokumen
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    Ajukan pertanyaan ke dokumen yang sudah diproses ke vector store.
                    AI akan menjawab berdasarkan isi dokumen, lengkap dengan rujukan chunk sumber.
                </p>
            </div>
            <x-primary-button type="button" wire:click="openStartModal">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Chat Baru
            </x-primary-button>
        </header>

        {{-- List sesi. --}}
        @if ($this->sessions->isEmpty())
            <div class="rounded-xl border border-dashed border-slate-300 bg-white py-16 text-center">
                <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-6 w-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                    </svg>
                </span>
                <p class="mt-3 text-sm font-medium text-slate-700">Belum ada sesi chat</p>
                <p class="mt-1 text-xs text-slate-500">
                    Mulai chat baru dengan memilih dokumen yang sudah diproses ke vector.
                </p>
                <x-primary-button type="button" wire:click="openStartModal" class="mt-4">
                    Mulai Chat
                </x-primary-button>
            </div>
        @else
            <ul class="space-y-2">
                @foreach ($this->sessions as $session)
                    <li wire:key="session-{{ $session->id }}">
                        <a
                            href="{{ route('chat.show', $session->id) }}"
                            wire:navigate
                            class="group flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md"
                        >
                            <span class="inline-flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-brand-50 text-brand-700">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                </svg>
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $session->title }}</p>
                                <p class="mt-0.5 truncate text-xs text-slate-500">
                                    Dokumen: {{ $session->document?->title ?? '—' }} ·
                                    {{ $session->messages_count }} pesan
                                </p>
                            </div>

                            <span class="hidden flex-none text-xs text-slate-400 sm:inline">
                                {{ $session->last_message_at?->diffForHumans() ?? $session->created_at->diffForHumans() }}
                            </span>

                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-none text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand-600">
                                <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Modal mulai chat baru. --}}
    @if ($showStartModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" wire:click="closeStartModal"></div>

            <div class="relative w-full max-w-lg rounded-xl bg-white shadow-2xl ring-1 ring-slate-200">
                <form wire:submit="startSession">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-slate-900">Mulai Chat Baru</h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Pilih dokumen yang sudah diproses ke vector untuk diajak chat.
                        </p>
                    </div>

                    <div class="space-y-3 px-6 py-5">
                        @if ($this->availableDocuments->isEmpty())
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                <p class="font-medium">Belum ada dokumen siap-chat</p>
                                <p class="mt-1 text-xs">
                                    Unggah PDF di menu Dokumen, lalu klik "Proses ke Vector" untuk membuat embedding.
                                </p>
                            </div>
                        @else
                            <x-input-label for="select-document" value="Dokumen" />
                            <select
                                wire:model="selectedDocumentId"
                                id="select-document"
                                class="block w-full rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30"
                                required
                            >
                                <option value="">— Pilih dokumen —</option>
                                @foreach ($this->availableDocuments as $doc)
                                    <option value="{{ $doc->id }}">{{ $doc->title }} ({{ $doc->total_chunks }} chunks)</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('selectedDocumentId')" class="mt-1.5" />
                        @endif
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
                        <x-secondary-button type="button" wire:click="closeStartModal">Batal</x-secondary-button>
                        @if (! $this->availableDocuments->isEmpty())
                            <x-primary-button>Mulai Chat</x-primary-button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
