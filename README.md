# PintarBelajar AI

> Asisten belajar berbasis **Retrieval-Augmented Generation (RAG)**. Unggah
> PDF materi → AI memecahnya jadi chunk → menjawab pertanyaanmu berdasar isi
> dokumen, lengkap dengan rujukan sumber. Dibangun untuk Tugas 1 Sertifikasi
> BNSP Web Developer.

[Lihat panduan teknis detail → ARSITEKTUR.md](ARSITEKTUR.md)

---

## 1. Tech Stack

| Lapisan          | Teknologi                              | Versi   | Fungsi |
| ---------------- | -------------------------------------- | ------- | ------ |
| Bahasa           | PHP                                    | 8.5     | Runtime backend |
| Framework        | Laravel                                | 13.x    | Routing, ORM, validasi, queue, mail |
| Frontend reaktif | Livewire 3 + Volt + Alpine.js          | 3.x     | UI interaktif single-stack (tanpa SPA terpisah) |
| Styling          | Tailwind CSS                           | 4.x     | Utility-first styling lewat `@theme` di CSS |
| Build tool       | Vite                                   | 8.x     | Bundling JS/CSS dengan `laravel-vite-plugin` |
| Auth             | Laravel Breeze (preset Livewire) + Pest | 2.x    | Register/login/lupa-sandi, role user/admin via kolom DB |
| Database         | PostgreSQL                             | 16      | Storage utama; container Docker image `pgvector/pgvector:pg16` |
| Vector store     | pgvector                               | 0.8.2   | Extension PostgreSQL untuk kolom `vector(768)` + HNSW index cosine |
| PDF parser       | `smalot/pdfparser`                     | 2.x     | Ekstraksi teks dari PDF saat upload |
| Embedding model  | Gemini `gemini-embedding-001`          | 768 dim | Ubah teks chunk → vector 768 dim via `outputDimensionality` |
| Chat model       | Gemini `gemini-2.5-flash-lite`         | -       | Generate jawaban RAG (cepat & hemat token) |
| Web server lokal | Laravel Herd                           | -       | Resolve domain `*.test` otomatis |
| Queue            | Driver `sync`                          | -       | Tanpa Redis / message broker — semua proses inline |
| Cache & Session  | Driver `database`                      | -       | Semua state di PostgreSQL |
| Testing          | Pest                                   | 4.x     | Test suite (auth, CRUD, upload, RAG, guardrail) |

---

## 2. Prasyarat

Pastikan tools berikut terpasang:

- **PHP 8.5** (cek: `php -v`)
- **Composer 2.x** (cek: `composer -V`)
- **Node.js 20+** dan **npm** (cek: `node -v`)
- **Docker Desktop** (Windows/Mac) atau **Docker Engine** (Linux)
- **Laravel Herd** — mengelola PHP-FPM lokal + resolve domain `.test`
- Git

---

## 3. Setup dari Nol

```bash
# 1. Clone & masuk ke folder
git clone <url-repo> pintarbelajar-ai
cd pintarbelajar-ai

# 2. Dependency PHP
composer install

# 3. Environment file
cp .env.example .env

# 4. Generate APP_KEY
php artisan key:generate

# 5. Start container PostgreSQL + pgvector
docker compose up -d

# 6. Aktifkan extension vector (sekitar 5-10 detik setelah container healthy)
docker exec pintarbelajar_db psql -U laravel -d pintarbelajar \
  -c "CREATE EXTENSION IF NOT EXISTS vector;"

# 7. Migrate + seed data demo (admin + 3 user + 4 subject + 4 dokumen)
php artisan migrate:fresh --seed

# 8. Frontend
npm install
npm run build
```

Seeder mencetak kredensial demo di akhir output:

```
Admin    → admin@pintarbelajar.test
User     → andi@pintarbelajar.test, rina@pintarbelajar.test, budi@pintarbelajar.test
Password → PintarBelajar2026!     (semua akun pakai password yang sama)
```

Buka **http://pintarbelajar-ai.test** di browser.

> Herd auto-resolve `pintarbelajar-ai.test` selama folder project berada di
> directory yang sudah di-parking oleh Herd (di mesin dev:
> `E:\Programming\Herd`).

---

## 4. Konfigurasi AI (Google Gemini)

Fitur AI butuh API key Gemini (gratis):

1. Buka **https://aistudio.google.com** → login dengan akun Google.
2. Klik **"Get API key"** → **"Create API key"**. Tidak butuh kartu kredit.
3. Salin key ke `.env`:

   ```env
   GEMINI_API_KEY=AIza...your-key-here...
   GEMINI_MODEL=gemini-2.5-flash-lite
   GEMINI_EMBEDDING_MODEL=gemini-embedding-001
   GEMINI_EMBEDDING_DIMENSIONS=768
   ```

4. `php artisan config:clear` bila perlu.

`.env` sudah ada di `.gitignore` — key tidak akan ter-commit. Bila bocor,
hapus di AI Studio dan buat baru.

Detail config lihat `config/gemini.php`.

---

## 5. Workflow Harian

```bash
docker compose up -d         # 1. Pastikan container DB hidup
npm run dev                  # 2. Vite dev server (hot reload)
                             # 3. Buka http://pintarbelajar-ai.test
```

Atau semua-sekaligus (Vite + queue listener + log tailing + serve):

```bash
composer dev
```

---

## 6. Fitur

### Sudah jadi (Tier 1)

| Fitur | Akses | Catatan |
| ----- | ----- | ------- |
| Auth lengkap | publik | Register, login, lupa kata sandi, verifikasi email |
| Landing page | publik | Hero, fitur, cara kerja, CTA — full responsive |
| Dashboard | semua user | Statistik, quick actions, dokumen terbaru, kartu role-aware |
| Manajemen Pengguna | admin | CRUD + ubah role; cegah hapus admin terakhir & self-delete |
| Manajemen Mata Pelajaran | admin (CRUD) · user (lihat) | Slug auto, icon + warna preset |
| Manajemen Dokumen | semua user (admin lihat semua) | Upload drag-and-drop PDF, parse teks, list/detail/delete |
| Chunking + Embedding ke pgvector | admin/user (trigger tombol) | Gemini `gemini-embedding-001`, 768 dim, HNSW cosine |
| **Chat with Document (RAG)** | semua user | Query rewriting heuristik, top-K=8 retrieval dengan threshold, 3-tier answer policy, citation klikable, guardrail injection |

### Sudah jadi (Tier 2)

| Fitur | Akses | Catatan |
| ----- | ----- | ------- |
| **Auto-Summary** | semua user | Tiga tingkat ringkasan (eksekutif, per bagian, poin kunci) dari isi dokumen; hasil di-cache di DB, bisa dibuat ulang |
| **Adaptive Quiz Generator** | semua user | Soal pilihan ganda / benar-salah / isian singkat dibuat dari chunk dokumen via JSON terstruktur Gemini; parsing aman + retry |
| **Pengerjaan & Scoring Kuis** | semua user | Kerjakan kuis, skor otomatis, review jawaban per soal + penjelasan, kuis lanjutan adaptif menyesuaikan skor |
| **Statistik belajar di dashboard** | semua user | Jumlah ringkasan, kuis dibuat, kuis dikerjakan, rata-rata skor |

### Roadmap berikutnya (Tier 3)

- Flashcard otomatis dari poin penting
- Dashboard analitik mendalam (progres belajar)

---

## 7. Catatan Port PostgreSQL

Container project ini meng-ekspos **port 5440** (bukan default 5432) supaya
tidak bentrok dengan PostgreSQL bawaan Herd atau project lain di mesin yang
sama. Konfigurasi:

- `docker-compose.yml`: `ports: ["5440:5432"]`
- `.env`: `DB_PORT=5440`

Bila port 5440 sudah dipakai di mesin lain, ganti kedua angka tersebut
konsisten.

---

## 8. Perintah Cepat

```bash
# Database
docker compose up -d                                                # Start container
docker compose down                                                 # Stop container
docker exec -it pintarbelajar_db psql -U laravel -d pintarbelajar   # psql shell

# Laravel
php artisan migrate:fresh --seed   # Reset + isi data demo
php artisan tinker                 # REPL
php artisan route:list             # Daftar route
php artisan test                   # Pest suite

# Frontend
npm run dev                        # Vite hot-reload
npm run build                      # Bundle production

# Diagnostik RAG
php artisan rag:verify-similarity         # Sanity check pgvector (vector buatan)
php artisan rag:test-pipeline {doc?}      # Index dokumen ke Gemini API + report
php artisan rag:test-chat {doc} "tanya"   # End-to-end Q&A
```

---

## 9. Dokumentasi Lebih Detail

| Topik | File |
|---|---|
| Arsitektur teknis lengkap (auth, upload, chunking, RAG, guardrail) | [`ARSITEKTUR.md`](ARSITEKTUR.md) |
| Konteks proyek untuk Claude Code (schema, gotchas, konvensi) | [`CLAUDE.md`](CLAUDE.md) |

---

## 10. Lisensi

Dibuat untuk Sertifikasi BNSP Web Developer. Kode bebas dipelajari untuk
tujuan edukasi.
