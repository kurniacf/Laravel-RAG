<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        $andi = User::query()->where('email', 'andi@pintarbelajar.test')->first();
        $rina = User::query()->where('email', 'rina@pintarbelajar.test')->first();
        $budi = User::query()->where('email', 'budi@pintarbelajar.test')->first();

        $matematika = Subject::query()->where('slug', 'matematika')->first();
        $fisika = Subject::query()->where('slug', 'fisika')->first();
        $biologi = Subject::query()->where('slug', 'biologi')->first();
        $programming = Subject::query()->where('slug', 'pemrograman-dasar')->first();

        if (! $andi || ! $rina || ! $budi || ! $matematika || ! $fisika || ! $biologi || ! $programming) {
            $this->command?->warn('UserSeeder/SubjectSeeder belum dijalankan — DocumentSeeder dilewati.');

            return;
        }

        // Empat dokumen demo. extracted_text sengaja cukup panjang per dokumen
        // (3-5 ribu karakter) supaya saat "Proses ke Vector" benar-benar
        // menghasilkan beberapa chunk (multi-chunk), bukan satu chunk besar.
        $documents = [
            [
                'user' => $andi,
                'subject' => $matematika,
                'title' => 'Pengantar Kalkulus Diferensial',
                'page_count' => 12,
                'word_count' => 3200,
                'days_ago' => 1,
                'text' => self::kalkulusText(),
            ],
            [
                'user' => $rina,
                'subject' => $fisika,
                'title' => 'Mekanika Klasik Bab 1',
                'page_count' => 18,
                'word_count' => 4900,
                'days_ago' => 2,
                'text' => self::mekanikaText(),
            ],
            [
                'user' => $budi,
                'subject' => $biologi,
                'title' => 'Struktur Sel dan Organel',
                'page_count' => 10,
                'word_count' => 2700,
                'days_ago' => 3,
                'text' => self::biologiText(),
            ],
            [
                'user' => $andi,
                'subject' => $programming,
                'title' => 'Algoritma dan Struktur Data',
                'page_count' => 20,
                'word_count' => 5600,
                'days_ago' => 4,
                'text' => self::algoritmaText(),
            ],
        ];

        $now = now();

        foreach ($documents as $doc) {
            Document::firstOrCreate(
                ['title' => $doc['title'], 'user_id' => $doc['user']->id],
                [
                    'subject_id' => $doc['subject']->id,
                    'original_filename' => Str::slug($doc['title']).'.pdf',
                    'file_path' => 'documents/seed/'.Str::uuid()->toString().'.pdf',
                    'file_size_bytes' => $doc['word_count'] * 350,
                    'status' => Document::STATUS_READY,
                    'page_count' => $doc['page_count'],
                    'word_count' => $doc['word_count'],
                    'total_chunks' => 0,
                    'extracted_text' => $doc['text'],
                    'processed_at' => $now->copy()->subDays($doc['days_ago']),
                ],
            );
        }

        // Sinkronkan documents_count di subjects.
        DB::statement('
            UPDATE subjects
            SET documents_count = (
                SELECT COUNT(*) FROM documents WHERE documents.subject_id = subjects.id
            )
        ');
    }

    // ───────── Konten dummy panjang per topik ─────────
    //
    // Tiap paragraf diulang/dirangkai supaya total karakter sekitar 4-5 ribu,
    // cukup untuk menghasilkan 2-3 chunk pada ChunkingService (chunkChars=2000,
    // overlap=200). Konten tetap masuk akal sebagai materi belajar singkat.

    private static function kalkulusText(): string
    {
        return implode(' ', [
            'Kalkulus diferensial adalah cabang matematika yang mempelajari laju perubahan suatu kuantitas terhadap kuantitas lain.',
            'Konsep inti yang dipelajari pertama kali adalah limit, yaitu nilai yang didekati oleh suatu fungsi ketika variabelnya mendekati nilai tertentu.',
            'Limit menjadi fondasi dari definisi turunan, yaitu kemiringan garis singgung pada kurva di suatu titik.',
            'Secara formal, turunan fungsi f di titik a didefinisikan sebagai limit dari (f(a+h) - f(a)) / h saat h mendekati nol.',
            'Notasi yang umum dipakai untuk turunan adalah f prima dari x, atau df per dx dalam notasi Leibniz.',
            'Aturan dasar diferensiasi mencakup aturan konstanta, aturan pangkat, aturan jumlah, aturan hasil kali, dan aturan hasil bagi.',
            'Aturan rantai menjadi alat penting untuk menurunkan komposisi fungsi, misalnya turunan dari sin(x kuadrat) dihitung lewat aturan rantai.',
            'Turunan dari fungsi trigonometri sin x adalah cos x, sedangkan turunan cos x adalah negatif sin x.',
            'Pada fungsi eksponensial, turunan dari e pangkat x adalah e pangkat x itu sendiri, sebuah sifat unik yang sangat berguna.',
            'Fungsi logaritma natural ln x memiliki turunan 1/x untuk x positif, sering muncul di aplikasi fisika dan ekonomi.',
            'Aplikasi turunan mencakup pencarian titik maksimum dan minimum lokal melalui uji turunan pertama dan turunan kedua.',
            'Titik kritis adalah titik di mana turunan pertama bernilai nol atau tidak terdefinisi; di titik ini fungsi bisa mencapai ekstremum.',
            'Uji turunan kedua membantu menentukan apakah suatu titik kritis adalah maksimum, minimum, atau titik belok.',
            'Konsep kelinieran lokal menyatakan bahwa di sekitar suatu titik, fungsi yang dapat diturunkan dapat didekati dengan garis singgungnya.',
            'Aproksimasi linier ini menjadi pondasi metode Newton untuk mencari akar persamaan secara numerik.',
            'Diferensial dy berhubungan dengan turunan melalui dy = f prima (x) dx, dipakai dalam estimasi error dan kalkulus lanjut.',
            'Turunan tingkat tinggi seperti f dua prima atau f tiga prima mengungkap perilaku kelengkungan dan keteraturan fungsi.',
            'Dalam aplikasi fisika, turunan posisi terhadap waktu menghasilkan kecepatan, dan turunan kecepatan menghasilkan percepatan.',
            'Latihan akhir bab biasanya mencakup soal turunan fungsi polinom, rasional, dan komposisi, ditutup dengan masalah optimasi sederhana.',
            'Pemahaman kalkulus diferensial menjadi prasyarat untuk kalkulus integral yang dibahas pada bab berikutnya.',
        ]);
    }

    private static function mekanikaText(): string
    {
        return implode(' ', [
            'Mekanika klasik adalah cabang fisika yang mempelajari gerak benda di bawah pengaruh gaya, tanpa mempertimbangkan efek relativistik maupun kuantum.',
            'Bab pertama biasanya membahas kinematika satu dimensi, yaitu deskripsi gerak tanpa membahas penyebabnya.',
            'Besaran kinematika utama mencakup posisi, perpindahan, kecepatan, dan percepatan, semuanya diukur sebagai fungsi waktu.',
            'Kecepatan rata-rata didefinisikan sebagai perpindahan dibagi selang waktu, sedangkan kecepatan sesaat adalah turunan posisi terhadap waktu.',
            'Percepatan adalah turunan kecepatan terhadap waktu; bila percepatan konstan, berlaku rumus kinematika klasik untuk gerak lurus berubah beraturan.',
            'Hukum Newton pertama menyatakan bahwa benda akan tetap diam atau bergerak lurus beraturan kecuali ada gaya netto yang mengubahnya.',
            'Hukum Newton kedua menyatakan F sama dengan m kali a, dengan F adalah gaya total, m massa benda, dan a percepatan yang dihasilkan.',
            'Hukum Newton ketiga menyatakan untuk setiap aksi terdapat reaksi yang sama besar tetapi berlawanan arah.',
            'Gaya gravitasi di permukaan bumi mendekati 9,8 meter per detik kuadrat dan menyebabkan percepatan jatuh bebas pada benda.',
            'Gerak parabola adalah kombinasi gerak lurus beraturan secara horizontal dan gerak lurus berubah beraturan secara vertikal.',
            'Konsep energi kinetik 1/2 m v kuadrat dan energi potensial m g h menjadi penghubung antara mekanika dengan termodinamika.',
            'Hukum kekekalan energi mekanik berlaku selama hanya gaya konservatif yang bekerja, misalnya gravitasi dan pegas ideal.',
            'Momentum linier p sama dengan m v, dan kekekalan momentum berlaku pada sistem tertutup tanpa gaya eksternal.',
            'Tumbukan dapat dibedakan menjadi tumbukan lenting sempurna, lenting sebagian, dan tidak lenting sama sekali.',
            'Pada tumbukan lenting sempurna, baik momentum maupun energi kinetik kekal; pada tumbukan tidak lenting, hanya momentum yang kekal.',
            'Pemahaman gaya gesek statis dan kinetis penting untuk analisis benda di bidang miring dan benda yang ditarik di permukaan kasar.',
            'Gaya pegas mengikuti hukum Hooke F = -k x, dengan k konstanta pegas dan x simpangan dari posisi setimbang.',
            'Pendulum sederhana berosilasi dengan periode yang sebanding akar kuadrat dari panjang tali dibagi percepatan gravitasi.',
            'Materi di bab ini menjadi fondasi untuk dinamika rotasi, gerak harmonik sederhana, dan mekanika fluida pada bab-bab selanjutnya.',
            'Latihan ditutup dengan kasus aplikatif seperti analisis benda meluncur di bidang miring, perhitungan ketinggian maksimum proyektil, dan analisis tumbukan dua benda.',
        ]);
    }

    private static function biologiText(): string
    {
        return implode(' ', [
            'Sel adalah unit struktural dan fungsional terkecil dari makhluk hidup; semua organisme tersusun dari satu atau lebih sel.',
            'Sel dapat dibagi menjadi dua kelompok besar: sel prokariotik tanpa membran inti, dan sel eukariotik yang memiliki inti sejati.',
            'Sel prokariotik umumnya ditemukan pada bakteri dan archaea, dengan material genetik berada bebas di sitoplasma dalam bentuk nukleoid.',
            'Sel eukariotik memiliki organel-organel terikat membran yang menjalankan fungsi spesifik di dalam sel.',
            'Membran sel atau membran plasma tersusun dari fosfolipid bilayer yang bersifat semipermeabel; mengatur masuk-keluarnya zat dari sel.',
            'Sitoplasma adalah cairan kental tempat organel berada; di dalamnya terjadi berbagai reaksi metabolisme penting.',
            'Inti sel atau nukleus berisi DNA dalam bentuk kromatin; merupakan pusat kendali aktivitas seluler termasuk pembelahan dan sintesis protein.',
            'Mitokondria sering disebut sebagai pembangkit tenaga sel karena menjadi tempat respirasi seluler yang menghasilkan ATP.',
            'Mitokondria memiliki DNA sendiri yang diwariskan secara maternal, menjadi bukti penting teori endosimbion.',
            'Retikulum endoplasma kasar dipenuhi ribosom dan berperan dalam sintesis protein, sedangkan RE halus terlibat dalam sintesis lipid dan detoksifikasi.',
            'Aparatus Golgi memodifikasi, mengemas, dan menyortir protein hasil sintesis sebelum dikirim ke tujuan akhir di dalam atau luar sel.',
            'Lisosom mengandung enzim hidrolitik untuk mendegradasi material yang masuk ke sel, organel rusak, dan zat asing.',
            'Pada sel tumbuhan terdapat dinding sel dari selulosa, kloroplas untuk fotosintesis, serta vakuola sentral yang besar.',
            'Kloroplas mengandung pigmen klorofil yang menangkap energi cahaya untuk mengubah karbon dioksida dan air menjadi glukosa.',
            'Sitoskeleton tersusun dari mikrofilamen, filamen intermediet, dan mikrotubulus; memberi bentuk sel dan memfasilitasi pergerakan organel.',
            'Pembelahan sel dapat berlangsung melalui mitosis untuk sel somatik, atau meiosis untuk sel gamet pada organisme yang bereproduksi seksual.',
            'Mitosis menghasilkan dua sel anak yang identik secara genetik dengan sel induk; meiosis menghasilkan empat sel dengan separuh jumlah kromosom.',
            'Diferensiasi sel memungkinkan terbentuknya beragam jenis sel khusus dari satu sel induk pluripoten selama perkembangan organisme.',
            'Pemahaman struktur dan fungsi organel menjadi pondasi untuk topik lanjut seperti genetika, biokimia, dan fisiologi.',
            'Latihan di bab ini biasanya berupa identifikasi organel pada gambar mikroskop dan deskripsi fungsi spesifik tiap organel.',
        ]);
    }

    private static function algoritmaText(): string
    {
        return implode(' ', [
            'Algoritma adalah urutan langkah terdefinisi dan terbatas yang dipakai untuk menyelesaikan suatu masalah atau melakukan komputasi.',
            'Sebuah algoritma yang baik harus tepat, terbatas, efisien, dan dapat dipahami; tiap langkah harus tidak ambigu dan harus berakhir dalam waktu hingga.',
            'Notasi pseudokode dan diagram alir biasa dipakai untuk mengomunikasikan algoritma sebelum diimplementasikan ke bahasa pemrograman spesifik.',
            'Analisis kompleksitas waktu menggambarkan bagaimana waktu eksekusi tumbuh seiring bertambahnya ukuran input, sering dinyatakan dengan notasi O besar.',
            'Algoritma pencarian linear berjalan dalam O(n) sedangkan pencarian biner berjalan dalam O(log n) tetapi mensyaratkan data terurut.',
            'Algoritma pengurutan dasar seperti bubble sort, selection sort, dan insertion sort berjalan dalam O(n kuadrat) di kasus rata-rata.',
            'Algoritma pengurutan yang lebih efisien seperti merge sort dan quick sort berjalan dalam O(n log n) di kasus rata-rata.',
            'Quick sort cepat dalam praktik namun memiliki kasus terburuk O(n kuadrat) bila pivot dipilih tidak tepat.',
            'Struktur data adalah cara penyimpanan dan pengorganisasian data agar dapat diakses dan dimodifikasi secara efisien.',
            'Array menyimpan data dalam blok memori berurutan dengan akses indeks dalam O(1) tetapi sulit untuk penyisipan di tengah.',
            'Linked list menyimpan data sebagai rangkaian node yang saling menunjuk; penyisipan dan penghapusan cepat tetapi akses elemen membutuhkan traversal.',
            'Stack mengikuti prinsip Last In First Out, umum dipakai untuk evaluasi ekspresi, pelacakan panggilan fungsi, dan algoritma backtracking.',
            'Queue mengikuti prinsip First In First Out, dipakai dalam penjadwalan proses, pemrosesan antrean, dan algoritma BFS pada graf.',
            'Hash table menyimpan pasangan kunci-nilai dengan akses rata-rata O(1) melalui fungsi hash, asalkan tabrakan dikelola dengan baik.',
            'Pohon biner dan variannya seperti binary search tree memberi keseimbangan antara penyisipan, penghapusan, dan pencarian yang efisien.',
            'Pohon biner pencarian seimbang seperti AVL atau Red-Black tree menjamin tinggi pohon logaritmik untuk performa konsisten.',
            'Graf merepresentasikan hubungan antar entitas dengan simpul dan sisi; bisa direpresentasikan sebagai matriks adjasensi atau daftar adjasensi.',
            'Algoritma jelajah graf BFS dan DFS menjadi pondasi untuk algoritma jalur terpendek, deteksi siklus, dan topological sort.',
            'Dynamic programming menyelesaikan masalah dengan memecahnya menjadi submasalah yang tumpang tindih dan menyimpan hasil agar tidak dihitung ulang.',
            'Pemilihan algoritma dan struktur data yang tepat dapat membuat program berjalan ribuan kali lebih cepat daripada solusi naif.',
        ]);
    }
}
