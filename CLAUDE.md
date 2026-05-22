# CLAUDE.md — PintarBelajar AI

> Konteks teknis untuk Claude. File ini bersifat **inward-facing**: berisi
> keputusan arsitektur, skema, dan constraint yang tidak bisa diturunkan dari
> kode begitu saja. Untuk panduan setup, lihat `README.md`.

---

## 1. Project Overview

**PintarBelajar AI** adalah platform belajar berbasis Retrieval-Augmented
Generation (RAG). Pengguna mengunggah dokumen materi (PDF), lalu sistem
memprosesnya menjadi chunk + embedding agar dapat diajak chat, diringkas,
dijadikan kuis, dan dijadikan flashcard.

Project ini dibangun sebagai Tugas 1 Sertifikasi BNSP Web Developer.

**Filosofi pengerjaan:** bangun CRUD manajemen sampai stabil (auth, pengguna,
mata pelajaran, dokumen, chunking + embedding) → baru bangun fitur AI di atas
fondasi itu (chat RAG, summary, quiz, flashcard).

---

## 2. Tech Stack

| Lapisan          | Teknologi                          | Versi   |
| ---------------- | ---------------------------------- | ------- |
| Bahasa           | PHP                                | 8.5     |
| Framework        | Laravel                            | 13.x    |
| Frontend         | Livewire 3 + Volt                  | 3.x     |
| Styling          | Tailwind CSS                       | 4.x     |
| Auth             | Laravel Breeze (preset Livewire)   | 2.x     |
| Database         | PostgreSQL                         | 16      |
| Vector store     | pgvector                           | 0.8.2   |
| Build tool       | Vite                               | 8.x     |
| Server lokal     | Laravel Herd                       | -       |
| Testing          | Pest                               | 4.x     |
| Embedding LLM    | Google Gemini `gemini-embedding-001` | 768 dim (via `outputDimensionality`) |

---

## 3. Constraints (HARUS dipatuhi)

Constraint berikut datang dari keputusan arsitektur. Melanggar = menyimpang
dari filosofi proyek.

- **Tanpa Redis.** `QUEUE_CONNECTION=sync`, `CACHE_STORE=database`,
  `SESSION_DRIVER=database`. Tidak ada message broker eksternal.
- **Tanpa Horizon, tanpa Reverb, tanpa Pusher.** Semua proses sinkron di
  request yang men-trigger-nya.
- **Komentar dan teks UI dalam Bahasa Indonesia.**
- **Frontend single-stack:** semua interaksi via Livewire/Volt + Alpine.js.
  Tidak ada SPA terpisah (React/Vue) untuk menghindari over-engineering.
- **Embedding dipanggil eksplisit lewat tombol** "Proses ke Vector", bukan
  otomatis di akhir upload. Tujuannya: hemat kuota Gemini API dan kontrol
  biaya.
- **PDF dibatasi 30 halaman dan 10 MB** untuk demo. Cocok dengan queue sync
  agar tidak timeout.
- **Jangan menambah dependency baru tanpa konfirmasi pengguna.**

---

## 4. Database Schema

> Semua tabel pakai snake_case, timestamps default Laravel.
> FK diset `ON DELETE CASCADE` kecuali disebut lain.

### 4.1 `users` (diperluas dari skeleton Laravel)

| Kolom              | Tipe         | Catatan                                         |
| ------------------ | ------------ | ----------------------------------------------- |
| id                 | bigserial PK |                                                 |
| name               | string       |                                                 |
| email              | string       | unique                                          |
| email_verified_at  | timestamp    | nullable                                        |
| password           | string       |                                                 |
| **role**           | string(16)   | `'user'` (default), atau `'admin'`              |
| remember_token     | string       | nullable                                        |
| created_at         | timestamp    |                                                 |
| updated_at         | timestamp    |                                                 |

CHECK constraint `role IN ('user', 'admin')` ditambahkan hanya di PostgreSQL.

### 4.2 `subjects` (mata pelajaran)

| Kolom             | Tipe         | Catatan                                |
| ----------------- | ------------ | -------------------------------------- |
| id                | bigserial PK |                                        |
| name              | string       | wajib                                  |
| slug              | string       | unique, auto dari name                 |
| icon              | string       | nullable, nama ikon internal (mis. `book`) |
| color_hex         | string(7)    | default `#059669` (emerald)            |
| documents_count   | integer      | default 0, denormalisasi               |
| created_at        | timestamp    |                                        |
| updated_at        | timestamp    |                                        |

### 4.3 `documents`

| Kolom              | Tipe         | Catatan                                                    |
| ------------------ | ------------ | ---------------------------------------------------------- |
| id                 | bigserial PK |                                                            |
| user_id            | FK users     | pemilik (kecuali admin lihat semua)                        |
| subject_id         | FK subjects  | nullable                                                   |
| title              | string       |                                                            |
| original_filename  | string       | nama file asli yang diunggah                               |
| file_path          | string       | relatif terhadap storage disk `local`                      |
| file_size_bytes    | integer      |                                                            |
| status             | enum         | `pending`, `processing`, `ready`, `failed`                 |
| page_count         | integer      | nullable, terisi setelah parse                             |
| word_count         | integer      | nullable                                                   |
| total_chunks       | integer      | default 0, terisi setelah chunking                         |
| extracted_text     | text         | nullable, hasil ekstraksi smalot/pdfparser                 |
| error_message      | text         | nullable, isi kalau status=`failed`                        |
| processed_at       | timestamp    | nullable                                                   |
| created_at         | timestamp    |                                                            |
| updated_at         | timestamp    |                                                            |

Index: `user_id`, `status`.

### 4.4 `document_chunks`

| Kolom         | Tipe          | Catatan                                              |
| ------------- | ------------- | ---------------------------------------------------- |
| id            | bigserial PK  |                                                      |
| document_id   | FK documents  | cascade                                              |
| chunk_index   | integer       | urutan chunk dalam dokumen, mulai 0                  |
| content       | text          | isi chunk (sekitar 512 token)                        |
| page_number   | integer       | nullable, perkiraan halaman asal                     |
| token_count   | integer       | nullable                                             |
| embedding     | `vector(768)` | nullable sampai EmbeddingService dipanggil           |
| created_at    | timestamp     |                                                      |
| updated_at    | timestamp     |                                                      |

Index: `(document_id, chunk_index)` unique. Index HNSW pada `embedding`
dengan operator `vector_cosine_ops` dibuat di migration (pgsql only).

### 4.5 `ai_jobs` (audit log pekerjaan AI)

| Kolom         | Tipe         | Catatan                                          |
| ------------- | ------------ | ------------------------------------------------ |
| id            | bigserial PK |                                                  |
| document_id   | FK documents | cascade                                          |
| job_type      | enum         | `parse`, `chunk`, `embed`                        |
| status        | enum         | `pending`, `running`, `completed`, `failed`      |
| started_at    | timestamp    | nullable                                         |
| finished_at   | timestamp    | nullable                                         |
| duration_ms   | integer      | nullable                                         |
| tokens_used   | integer      | nullable, untuk job embed                        |
| error_message | text         | nullable                                         |
| created_at    | timestamp    |                                                  |
| updated_at    | timestamp    |                                                  |

Index: `(document_id, job_type)`, `status`.

### 4.6 `chat_sessions`

| Kolom            | Tipe         | Catatan                                          |
| ---------------- | ------------ | ------------------------------------------------ |
| id               | bigserial PK |                                                  |
| user_id          | FK users     | cascade                                          |
| document_id      | FK documents | cascade — sesi terikat ke satu dokumen           |
| title            | string       | biasanya judul dokumen (auto saat session dibuat) |
| last_message_at  | timestamp    | nullable, di-update setiap balasan AI            |
| created_at       | timestamp    |                                                  |
| updated_at       | timestamp    |                                                  |

Index: `(user_id, last_message_at)`, `document_id`.

### 4.7 `chat_messages`

| Kolom             | Tipe                | Catatan                                          |
| ----------------- | ------------------- | ------------------------------------------------ |
| id                | bigserial PK        |                                                  |
| chat_session_id   | FK chat_sessions    | cascade                                          |
| role              | string(16)          | `user` atau `assistant` (CHECK constraint pgsql) |
| content           | text                | isi pesan                                        |
| cited_chunk_ids   | jsonb / json        | nullable, array ID `document_chunks` rujukan     |
| tokens_used       | integer             | nullable, untuk pesan assistant                  |
| created_at        | timestamp           |                                                  |
| updated_at        | timestamp           |                                                  |

Index: `(chat_session_id, created_at)`.

---

## 5. Daftar Role

Sistem otorisasi berbasis **kolom `role`** di tabel `users`. Sengaja sederhana
— tidak pakai Spatie Permission supaya footprint dependency kecil.

| Role      | Hak Akses                                                                                                |
| --------- | -------------------------------------------------------------------------------------------------------- |
| **user**  | Default saat register. Dapat unggah, melihat, dan menghapus dokumen miliknya sendiri. Bisa chat dengan dokumennya. Boleh **melihat** daftar mata pelajaran (untuk picker upload). Tidak boleh kelola subjects atau pengguna lain. |
| **admin** | Semua hak user. Tambahan: kelola Pengguna (CRUD users termasuk ubah role), kelola Mata Pelajaran (CRUD subjects), lihat dokumen milik pengguna lain. |

**Enforcement:**
- Cek role lewat helper di model `User`: `$user->isAdmin()`, `$user->isUser()`.
- Gate `manage-users` & `manage-subjects` di `AppServiceProvider::boot()`.
- Route dilindungi via `middleware('can:manage-users')`.
- Menu sidebar disembunyikan bila role tidak mengizinkan.

---

## 6. Gotchas

Hal-hal yang gampang menjebak:

- **Port PostgreSQL host = 5440** (bukan 5432). Alasan: 5432 dipakai postgres
  bawaan Herd, 5434 & 15432 dipakai container project lain. Konfigurasi ada
  di `docker-compose.yml` (`5440:5432`) dan `.env` (`DB_PORT=5440`).
- **Queue sync = parsing PDF berjalan di request user.** Karena itu ada batas
  30 halaman / 10 MB. Jangan hilangkan batas ini tanpa migrasi ke queue
  beneran.
- **Embedding tidak otomatis** setelah parse. Harus eksplisit lewat tombol
  "Proses ke Vector" di halaman dokumen. Ini desain, bukan bug.
- **HNSW index pada `document_chunks.embedding`** dibuat di migration lewat
  raw `DB::statement` (pgsql only). Laravel belum punya syntax built-in untuk
  vector index.
- **Database testing pakai SQLite `:memory:`** (lihat `phpunit.xml`). Test
  yang butuh fitur PostgreSQL khusus (vector, JSONB ops) HARUS pakai
  PostgreSQL — verifikasi end-to-end pgvector via artisan command
  `rag:verify-similarity`.
- **`extracted_text` bisa besar.** Sengaja disimpan di tabel `documents`
  bukan kolom terpisah — query simpel, dan PDF dibatasi 30 halaman jadi
  ukuran terkendali.
- **`<title>` tanpa em-dash/hyphen.** Pemisah judul tab pakai middot `·`
  (mis. "Dashboard · PintarBelajar AI"), bukan `—` atau `-`, agar tidak
  terkesan template AI generik.
- **Gemini Embeddings butuh `outputDimensionality: 768`.** Model
  `gemini-embedding-001` default mengembalikan 3072 dim. EmbeddingService
  selalu kirim `outputDimensionality` di body request agar sinkron dengan
  kolom `vector(768)`. Model lama (`text-embedding-001` / `004`) sudah
  dihapus dari endpoint v1beta — JANGAN ganti default tanpa cek
  `ListModels` API dulu.
- **Urutan migration chat penting.** `create_chat_messages_table` punya FK
  ke `chat_sessions`, jadi timestamp filenya harus LEBIH BESAR daripada
  `create_chat_sessions_table` (alfabetis Laravel = kronologis).

---

## 7. Roadmap (status per fase)

### Fondasi & CRUD manajemen (sudah selesai)
- [x] Setup PostgreSQL 16 + pgvector via Docker (port 5440)
- [x] Koneksi Laravel ke PostgreSQL
- [x] Auth Laravel Breeze (preset Livewire + Pest), split-screen redesign
- [x] Layout sidebar aplikasi dengan role badge
- [x] Migration `role` ke users + Gate (`manage-users`, `manage-subjects`)
- [x] CRUD Manajemen Pengguna (admin only)
- [x] CRUD Mata Pelajaran (admin kelola; user lihat)
- [x] Upload + parse PDF + Manajemen Dokumen
- [x] Chunking + Embedding ke pgvector (trigger eksplisit)
- [x] Verifikasi similarity via `php artisan rag:verify-similarity`

### Polish UI/UX (sudah selesai)
- [x] Penyederhanaan role jadi user/admin saja
- [x] Title halaman tanpa em-dash
- [x] Landing page produk
- [x] Polish Login & Register (animasi halus, dekorasi)
- [x] Redesign dashboard (statistik hidup, quick actions, dokumen terbaru)
- [x] Seeder realistis (admin + user Indonesia + subject + dokumen dummy)
- [x] Konfigurasi Gemini (`config/gemini.php` + placeholder env)
- [x] Drag-and-drop upload dokumen
- [x] Empty state berbeda antara "belum ada data" dan "filter tidak ketemu"

### Tier 1 RAG (sudah selesai)
- [x] Chat with Document (RAG) — embed query, retrieve top-5 chunks via pgvector cosine, prompt anti-halusinasi, Gemini Flash-Lite generate, citation di UI
- [x] `RagService`, `GeminiChatService`, model `ChatSession` + `ChatMessage`
- [x] Riwayat chat per dokumen tersimpan dan dapat dibuka kembali

### Berikutnya (Tier 2)
- [ ] Ringkasan otomatis per dokumen
- [ ] Generate kuis dari dokumen
- [ ] Flashcard dari poin penting
- [ ] Dashboard analitik (progres belajar)

---

## 7b. Catatan Teknis RAG

Parameter kunci pipeline RAG (di-tune untuk demo, bisa di-tweak per use case):

| Parameter             | Nilai                          | Lokasi                                  |
| --------------------- | ------------------------------ | --------------------------------------- |
| Chunk size            | 2000 karakter (~512 token)     | `ChunkingService::__construct`          |
| Chunk overlap         | 200 karakter (~50 token)       | `ChunkingService::__construct`          |
| Token estimasi        | 1 token ≈ 4 karakter           | `ChunkingService::estimateTokens`       |
| Embedding dimensi     | 768                            | `config/gemini.php` + `outputDimensionality` |
| Embedding model       | `gemini-embedding-001`         | env `GEMINI_EMBEDDING_MODEL`            |
| Embedding task type   | `RETRIEVAL_DOCUMENT` (indexing) / `RETRIEVAL_QUERY` (chat) | `EmbeddingService::embed(string, taskType)` |
| Top-K retrieval       | 5 chunks                       | `RagService::TOP_K`                     |
| Distance metric       | cosine (`embedding <=> ?::vector`) | raw SQL di `RagService::ask`        |
| Chat model            | `gemini-2.5-flash-lite`        | env `GEMINI_MODEL`                      |
| Temperature           | 0.2 (low, factual)             | `RagService::ask` → `GeminiChatService` |
| Max output tokens     | 1024                           | `RagService::ask` → `GeminiChatService` |

**System instruction anti-halusinasi** di `RagService::ask`:
1. Jawab HANYA dari konteks `[CHUNK 1]`–`[CHUNK N]`.
2. Tidak ada di konteks → eksplisit bilang "Maaf, informasi tersebut tidak ditemukan dalam dokumen yang tersedia."
3. Jangan tambahkan pengetahuan umum, jangan mengarang fakta.
4. Singkat (2-5 kalimat), Bahasa Indonesia, sebut nomor chunk yang relevan.

**Trigger:**
- Indexing chunk + embed: tombol "Proses ke Vector" di list Dokumen (hanya untuk `status=ready && total_chunks=0`)
- Chat: route `/chat` (index sesi) + `/chat/{session}` (room). Tombol "Chat" di list Dokumen muncul bila `total_chunks > 0`.

**Sanity check command:**
- `php artisan rag:verify-similarity` — uji pgvector dengan vector buatan
- `php artisan rag:test-pipeline {document?}` — index dokumen ke Gemini API asli, lapor durasi + token + similarity ordering
- `php artisan rag:test-chat {document} "pertanyaan"` — uji satu Q&A end-to-end

---

## 8. Konvensi Kode

- **Komentar dalam Bahasa Indonesia.** Method/variable tetap bahasa Inggris
  untuk konsistensi dengan ekosistem Laravel.
- **Penamaan tabel jamak** (`documents`), **model tunggal** (`Document`),
  **PascalCase** untuk model dan class.
- **Komponen Livewire** ditempatkan di `app/Livewire/<Domain>/` dan
  view-nya di `resources/views/livewire/<domain>/`.
- **Volt single-file** dipakai untuk halaman sederhana (mis. dashboard);
  full Livewire class untuk komponen kompleks (CRUD dengan banyak action).
- **Service class** ditempatkan di `app/Services/<Domain>/`
  (mis. `app/Services/Rag/ChunkingService.php`).
- **Form Request** dipakai bila validasi >5 rule, selain itu inline di
  komponen Livewire.
- **Test Pest** untuk alur kritis: auth, CRUD utama, upload, embedding,
  seeder. Database testing pakai SQLite `:memory:`.

---

## 9. Variabel Environment Penting

```env
APP_NAME="PintarBelajar AI"
APP_URL=http://pintarbelajar-ai.test
APP_LOCALE=id

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5440
DB_DATABASE=pintarbelajar
DB_USERNAME=laravel
DB_PASSWORD=secret

QUEUE_CONNECTION=sync
CACHE_STORE=database
SESSION_DRIVER=database

# Google Gemini — ambil API key dari https://aistudio.google.com (gratis).
GEMINI_API_KEY=
GEMINI_MODEL=gemini-2.5-flash-lite
GEMINI_EMBEDDING_MODEL=gemini-embedding-001
GEMINI_EMBEDDING_DIMENSIONS=768
```

---

## 10. Perintah Cepat

```bash
# DB
docker compose up -d
docker exec -it pintarbelajar_db psql -U laravel -d pintarbelajar

# Laravel
php artisan migrate:fresh --seed   # reset + isi data dummy
php artisan test
php artisan tinker
php artisan rag:verify-similarity  # sanity check pgvector

# Frontend
npm run dev
npm run build
```
