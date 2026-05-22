@php
    use App\Models\ChatMessage;
    $lookup = $this->chunkLookup;
@endphp

<div
    class="flex h-[calc(100vh-4rem)] flex-col"
    x-data="{
        scrollBottom() {
            this.$nextTick(() => {
                const el = this.$refs.scrollContainer;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },
    }"
    x-init="scrollBottom()"
    x-on:chat-scroll-bottom.window="scrollBottom()"
>

    {{-- Header sesi. --}}
    <header class="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 sm:px-6 lg:px-8">
        <a
            href="{{ route('chat.index') }}"
            wire:navigate
            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
            title="Kembali"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                <path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z" clip-rule="evenodd" />
            </svg>
        </a>
        <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Sesi Chat</p>
            <h1 class="truncate text-base font-semibold text-slate-900">{{ $session->title }}</h1>
        </div>
        <div class="ms-auto hidden text-xs text-slate-500 sm:flex sm:items-center sm:gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-0.5 font-semibold text-brand-700 ring-1 ring-inset ring-brand-600/20">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                </svg>
                {{ $session->document?->total_chunks ?? 0 }} chunks
            </span>
        </div>
    </header>

    {{-- Daftar pesan. --}}
    <div
        x-ref="scrollContainer"
        class="flex-1 overflow-y-auto bg-slate-50 px-4 py-6 sm:px-6 lg:px-8"
    >
        <div class="mx-auto flex max-w-3xl flex-col gap-6">

            @forelse ($session->messages as $message)
                @if ($message->role === ChatMessage::ROLE_USER)
                    {{-- Bubble user (kanan). --}}
                    <div class="flex justify-end" wire:key="msg-{{ $message->id }}">
                        <div class="max-w-[80%] rounded-2xl rounded-tr-sm bg-brand-600 px-4 py-2.5 text-sm text-white shadow-sm">
                            {{ $message->content }}
                        </div>
                    </div>
                @else
                    {{-- Bubble assistant (kiri) + citation. --}}
                    <div
                        class="flex justify-start"
                        wire:key="msg-{{ $message->id }}"
                        x-data="{ openChunk: null }"
                    >
                        <div class="max-w-[85%] space-y-2">
                            <div @class([
                                'rounded-2xl rounded-tl-sm px-4 py-3 text-sm leading-relaxed shadow-sm ring-1',
                                'bg-white text-slate-800 ring-slate-200' => $message->source !== ChatMessage::SOURCE_GENERAL,
                                'bg-amber-50/50 text-slate-800 ring-amber-200' => $message->source === ChatMessage::SOURCE_GENERAL,
                            ])>
                                <p class="whitespace-pre-wrap">{{ $message->content }}</p>
                            </div>

                            {{-- Badge sumber khusus (general / refused). --}}
                            @if ($message->source === ChatMessage::SOURCE_GENERAL)
                                <div class="flex items-center gap-1.5 px-1">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/20">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3 w-3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                                        </svg>
                                        Pengetahuan umum
                                    </span>
                                    <span class="text-[10px] text-slate-500">jawaban di luar isi dokumen</span>
                                </div>
                            @elseif ($message->source === ChatMessage::SOURCE_REFUSED)
                                <div class="flex items-center gap-1.5 px-1">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700 ring-1 ring-inset ring-slate-600/20">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3 w-3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                                        </svg>
                                        Tidak dijawab
                                    </span>
                                </div>
                            @endif

                            @if (! empty($message->cited_chunk_ids))
                                <div class="flex flex-wrap items-center gap-1.5 px-1">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Sumber:</span>
                                    @foreach ($message->cited_chunk_ids as $i => $chunkId)
                                        @php $chunk = $lookup[$chunkId] ?? null; @endphp
                                        @if ($chunk)
                                            <button
                                                type="button"
                                                @click="openChunk = openChunk === {{ $chunkId }} ? null : {{ $chunkId }}"
                                                class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700 ring-1 ring-inset ring-brand-600/20 transition hover:bg-brand-100"
                                            >
                                                Chunk #{{ $chunk['chunk_index'] + 1 }}
                                            </button>
                                            <div
                                                x-show="openChunk === {{ $chunkId }}"
                                                x-collapse
                                                x-cloak
                                                class="basis-full"
                                            >
                                                <div class="mt-1 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs leading-relaxed text-slate-700">
                                                    <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                                                        Chunk #{{ $chunk['chunk_index'] + 1 }}
                                                    </p>
                                                    {{ Str::limit($chunk['content'], 600, '...') }}
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            @if ($message->tokens_used)
                                <p class="px-1 text-[10px] text-slate-400">
                                    {{ $message->tokens_used }} token · {{ $message->created_at->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                    </div>
                @endif
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white py-12 text-center">
                    <span class="mx-auto inline-flex h-10 w-10 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                    </span>
                    <p class="mt-3 text-sm font-medium text-slate-700">Mulai bertanya tentang dokumen ini</p>
                    <p class="mt-1 text-xs text-slate-500">
                        Tulis pertanyaan di kotak di bawah. AI akan menjawab berdasarkan isi dokumen,
                        dengan rujukan chunk yang relevan.
                    </p>
                </div>
            @endforelse

            {{-- Loading indicator saat menunggu jawaban. --}}
            <div wire:loading wire:target="ask" class="flex justify-start">
                <div class="inline-flex items-center gap-2 rounded-2xl rounded-tl-sm bg-white px-4 py-2.5 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
                    <svg class="h-4 w-4 animate-spin text-brand-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4l3-3-3-3v4a8 8 0 1 0 8 8h-4l3 3 3-3h-4a8 8 0 0 1-8 8z"></path>
                    </svg>
                    AI sedang menyusun jawaban...
                </div>
            </div>

            @if ($errorMessage)
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errorMessage }}
                </div>
            @endif
        </div>
    </div>

    {{-- Input. --}}
    <form
        wire:submit="ask"
        class="border-t border-slate-200 bg-white px-4 py-3 sm:px-6 lg:px-8"
    >
        <div class="mx-auto flex max-w-3xl items-end gap-2">
            <div class="flex-1">
                <label for="chat-input" class="sr-only">Pertanyaan</label>
                <textarea
                    wire:model="question"
                    id="chat-input"
                    rows="1"
                    placeholder="Tanyakan sesuatu tentang dokumen ini..."
                    class="block w-full resize-none rounded-xl border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30 disabled:cursor-not-allowed disabled:bg-slate-50"
                    x-on:keydown.enter.prevent="$el.form.requestSubmit()"
                    wire:loading.attr="disabled"
                    wire:target="ask"
                ></textarea>
                <x-input-error :messages="$errors->get('question')" class="mt-1.5" />
            </div>
            <x-primary-button class="h-[42px]" wire:loading.attr="disabled" wire:target="ask">
                <span wire:loading.remove wire:target="ask">Kirim</span>
                <span wire:loading wire:target="ask">…</span>
            </x-primary-button>
        </div>
    </form>
</div>
