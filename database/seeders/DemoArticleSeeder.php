<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Support\Images;
use Illuminate\Database\Seeder;

/** Artikel contoh dengan sampul ilustrasi. Hapus/ganti sebelum dipakai klien. */
class DemoArticleSeeder extends Seeder
{
    public function run(): void
    {
        if (Article::exists()) {
            return;
        }

        $author = config('store.name').' Tim';

        $items = [
            ['cover-stok', 'Info toko', 'Kenapa stok di web selalu sama dengan di toko',
             'Satu angka stok untuk kasir dan toko online, jadi tidak ada pesanan yang batal karena barang ternyata habis.',
             "Pernah memesan barang online lalu dikabari bahwa barangnya habis? Di toko kami hal itu sengaja dibuat sangat jarang terjadi.\n\n## Satu sumber stok\n\nKasir di toko dan situs ini memakai **satu catatan stok yang sama**. Begitu kasir menjual sebungkus kopi, angka di situs ikut turun dalam hitungan detik. Begitu juga sebaliknya saat kamu memesan lewat situs.\n\n## Stok langsung dipesankan\n\nSaat pesanan dibuat, barangnya langsung kami sisihkan untukmu. Kalau pesanan dibatalkan, stoknya kembali otomatis.\n\n## Apa artinya untukmu\n\n- Label *Stok tersedia* artinya barangnya memang ada di rak.\n- Label *Sisa sekian* muncul saat stok menipis.\n- Tombol beli otomatis nonaktif saat barang habis.\n\n> Harga dan stok yang kamu lihat adalah harga dan stok yang kami pegang.\n\nAda pertanyaan soal stok? [Hubungi kami](/kontak) kapan saja."],
            ['cover-kopi', 'Tips', 'Memilih gilingan kopi: halus atau kasar?',
             'Halus untuk tubruk dan espresso, kasar untuk seduh manual. Ini panduan singkatnya.',
             "Kopi yang sama bisa terasa sangat berbeda hanya karena tingkat gilingannya. Berikut panduan cepat memilihnya.\n\n## Gilingan halus\n\nCocok untuk **kopi tubruk**, kopi susu, dan mesin espresso. Air bersentuhan dengan lebih banyak permukaan bubuk, jadi rasa keluar lebih cepat dan pekat.\n\n## Gilingan kasar\n\nCocok untuk **french press**, cold brew, dan seduh manual. Rasa keluar perlahan sehingga lebih bersih dan tidak terlalu pahit.\n\n## Berapa banyak per cangkir?\n\n| Cara seduh | Kopi | Air |\n|---|---|---|\n| Tubruk | 2 sendok teh | 150 ml |\n| French press | 15 g | 250 ml |\n| Cold brew | 50 g | 500 ml |\n\n## Simpan dengan benar\n\nSimpan di wadah tertutup, jauh dari panas dan lembap. Kopi bubuk paling enak dihabiskan dalam 3 sampai 4 minggu setelah dibuka.\n\nSemua kopi kami tersedia dalam dua pilihan gilingan, lihat di halaman [produk](/produk)."],
            ['cover-dapur', 'Tips', 'Cara menyimpan beras agar tahan lama dan bebas kutu',
             'Tiga kebiasaan sederhana supaya beras tetap pulen sampai butir terakhir.',
             "Beras yang disimpan sembarangan mudah apek dan berkutu. Tiga kebiasaan ini membantu.\n\n1. **Pakai wadah kedap udara.** Pindahkan beras dari karung ke wadah tertutup rapat.\n2. **Letakkan di tempat sejuk dan kering.** Hindari dekat kompor atau tempat yang lembap.\n3. **Habiskan yang lama dulu.** Tulis tanggal beli di wadah.\n\n## Kalau sudah ada kutu\n\nJemur beras tipis-tipis selama beberapa jam, lalu ayak. Jangan dicuci terlalu lama sebelum dimasak karena nutrisi di permukaan bulir ikut terbuang.\n\n## Pilih kemasan yang pas\n\nKeluarga kecil lebih cocok kemasan 5 kg supaya selalu segar. Usaha makan bisa mengambil 25 kg untuk harga per kilo yang lebih hemat."],
            ['cover-sekolah', 'Info toko', 'Daftar perlengkapan sekolah yang paling sering dicari',
             'Buku tulis, pulpen, dan kertas: pilihan yang sering diborong orang tua menjelang tahun ajaran baru.',
             "Menjelang tahun ajaran baru, rak alat tulis kami paling ramai. Ini yang paling sering dicari.\n\n## Buku tulis 38 lembar\n\nKertas halus dan tidak tembus tinta, sampul tebal. Tersedia tiga warna supaya mudah membedakan mata pelajaran.\n\n## Pulpen gel\n\nUjung 0.5 mm, tinta cepat kering. Beli satu lusin supaya tidak kehabisan di tengah ujian.\n\n## Kertas HVS A4\n\nUntuk tugas cetak dan fotokopi. Tersedia gramasi 70 dan 80.\n\nSemua barang ini bisa diambil di toko tanpa ongkos kirim. Lihat etalase [Perlengkapan sekolah](/etalase/sekolah)."],
        ];

        foreach ($items as $i => [$cover, $topic, $title, $excerpt, $body]) {
            $file = database_path("seeders/demo-images/{$cover}.jpg");
            $paths = is_file($file) ? Images::storeWide($file) : ['path' => null, 'card_path' => null];

            Article::create([
                'title' => $title, 'slug' => Article::uniqueSlug($title), 'topic' => $topic, 'excerpt' => $excerpt, 'body' => $body,
                'cover_path' => $paths['path'], 'cover_card_path' => $paths['card_path'], 'cover_alt' => $title,
                'author' => $author, 'is_published' => true, 'published_at' => now()->subDays(3 + $i * 6),
            ]);
        }
    }
}
