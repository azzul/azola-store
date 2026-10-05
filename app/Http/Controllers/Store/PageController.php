<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Client;
use App\Models\ContactMessage;
use App\Models\Product;
use App\Models\Testimonial;
use App\Support\Seo;
use Illuminate\Http\Request;

/** Halaman profil toko: tentang, kontak, FAQ, klien, dan kebijakan. */
class PageController extends Controller
{
    public function about()
    {
        return view('store.pages.about', [
            'seo' => $this->seo('Tentang kami', 'Kenali '.config('store.name').': cerita, nilai yang kami pegang, dan cara kami menjaga stok serta harga tetap jujur.', 'Tentang kami', route('about')),
            'products' => Product::online()->count(),
            'categories' => Category::count(),
            'reviews' => Testimonial::summary(),
            'clients' => Client::published()->limit(8)->get(),
        ]);
    }

    public function contact()
    {
        return view('store.pages.contact', [
            'seo' => $this->seo('Kontak', 'Hubungi '.config('store.name').' lewat WhatsApp, email, atau datang langsung ke toko. Lihat jam buka dan lokasi.', 'Kontak', route('contact')),
        ]);
    }

    public function contactSend(Request $request)
    {
        // Kolom "website" disembunyikan dari manusia: kalau terisi, ini bot. Pura-pura berhasil saja.
        if ($request->filled('website')) {
            return redirect()->route('contact')->with('sent', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40', 'required_without:email'],
            'email' => ['nullable', 'email', 'max:120', 'required_without:phone'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'phone.required_without' => 'Isi nomor telepon atau email supaya kami bisa membalas.',
            'email.required_without' => 'Isi nomor telepon atau email supaya kami bisa membalas.',
            'message.min' => 'Tulis pesan minimal 10 karakter.',
        ]);

        ContactMessage::create($data + ['ip' => $request->ip()]);

        return redirect()->route('contact')->with('sent', true);
    }

    public function faq()
    {
        $faq = config('store.faq');

        return view('store.pages.faq', [
            'faq' => $faq,
            'seo' => $this->seo('Pertanyaan umum', 'Jawaban untuk pertanyaan yang sering diajukan pembeli: stok, pembayaran, pengiriman, dan pengambilan di toko.', 'Pertanyaan umum', route('faq'), [Seo::faq($faq)]),
        ]);
    }

    public function clients()
    {
        $clients = Client::published()->get();

        return view('store.pages.clients', [
            'clients' => $clients,
            'seo' => $this->seo('Klien dan mitra', 'Perusahaan, instansi, dan komunitas yang berbelanja atau bekerja sama dengan '.config('store.name').'.', 'Klien dan mitra', route('clients'), [], $clients->isEmpty()),
        ]);
    }

    public function privacy()
    {
        return view('store.pages.privacy', ['seo' => $this->seo('Kebijakan privasi', 'Data apa yang kami simpan saat kamu berbelanja di '.config('store.name').', untuk apa, dan bagaimana kami menjaganya.', 'Kebijakan privasi', route('privacy'))]);
    }

    public function terms()
    {
        return view('store.pages.terms', ['seo' => $this->seo('Syarat dan ketentuan', 'Aturan berbelanja di '.config('store.name').': harga, stok, pembayaran, dan pengiriman.', 'Syarat dan ketentuan', route('terms'))]);
    }

    public function returns()
    {
        return view('store.pages.returns', ['seo' => $this->seo('Pengembalian dan pembatalan', 'Cara membatalkan pesanan atau mengembalikan barang di '.config('store.name').'.', 'Pengembalian dan pembatalan', route('returns'))]);
    }

    private function seo(string $title, string $description, string $crumb, string $url, array $extra = [], bool $noindex = false): array
    {
        return Seo::page([
            'title' => $title,
            'description' => $description,
            'canonical' => $url,
            'robots' => $noindex ? 'noindex,follow' : null,
            'jsonld' => array_merge([Seo::breadcrumbs([['Beranda', url('/')], [$crumb, $url]])], $extra),
        ]);
    }
}
