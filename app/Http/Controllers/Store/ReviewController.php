<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Support\Seo;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index()
    {
        $summary = Testimonial::summary();
        $reviews = Testimonial::published()->latest('id')->paginate(12);

        $jsonld = [Seo::breadcrumbs([['Beranda', url('/')], ['Ulasan pelanggan', route('reviews')]])];
        if ($summary['count'] > 0) {
            $jsonld[] = array_merge(Seo::store(), ['aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $summary['average'],
                'reviewCount' => (string) $summary['count'],
                'bestRating' => '5',
                'worstRating' => '1',
            ]]);
        }

        return view('store.pages.reviews', [
            'reviews' => $reviews,
            'summary' => $summary,
            'seo' => Seo::page([
                'title' => 'Ulasan pelanggan',
                'description' => 'Pengalaman pelanggan berbelanja di '.config('store.name').'. Ulasan ditampilkan setelah kami periksa, tanpa disunting.',
                'canonical' => route('reviews'),
                'robots' => $summary['count'] === 0 ? 'noindex,follow' : null,
                'jsonld' => $jsonld,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        if ($request->filled('website')) {
            return redirect()->route('reviews')->with('sent', true); // bot
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'role' => ['nullable', 'string', 'max:80'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:10', 'max:600'],
        ], [
            'rating.required' => 'Pilih jumlah bintang.',
            'body.min' => 'Tulis ulasan minimal 10 karakter.',
        ]);

        // Masuk sebagai draf: tampil di web hanya setelah admin menyetujui.
        Testimonial::create($data + ['source' => 'customer', 'is_published' => false]);

        return redirect()->route('reviews')->with('sent', true);
    }
}
