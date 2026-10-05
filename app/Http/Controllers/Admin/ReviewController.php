<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab') === 'tampil' ? 'tampil' : 'menunggu';

        return view('admin.reviews.index', [
            'tab' => $tab,
            'reviews' => Testimonial::where('is_published', $tab === 'tampil')->latest('id')->paginate(20)->withQueryString(),
            'pending' => Testimonial::where('is_published', false)->count(),
            'shown' => Testimonial::published()->count(),
        ]);
    }

    /** Ulasan yang dicatat admin sendiri (mis. dari WhatsApp), langsung tampil. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'role' => ['nullable', 'string', 'max:80'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:10', 'max:600'],
        ]);

        Testimonial::create($data + ['source' => 'admin', 'is_published' => true]);

        return back()->with('ok', 'Ulasan ditambahkan dan langsung tampil.');
    }

    public function toggle(Testimonial $review)
    {
        $review->update(['is_published' => ! $review->is_published]);

        return back()->with('ok', $review->is_published ? 'Ulasan ditampilkan.' : 'Ulasan disembunyikan.');
    }

    public function destroy(Testimonial $review)
    {
        $review->delete();

        return back()->with('ok', 'Ulasan dihapus.');
    }
}
