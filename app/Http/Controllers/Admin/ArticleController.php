<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Support\Images;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ArticleController extends Controller
{
    public function index()
    {
        return view('admin.articles.index', ['articles' => Article::orderByDesc('published_at')->orderByDesc('id')->paginate(20)]);
    }

    public function create()
    {
        return view('admin.articles.form', ['article' => new Article(['is_published' => false, 'published_at' => now()])]);
    }

    public function store(Request $request)
    {
        [$data, $publish] = $this->validated($request);

        $article = new Article($data);
        $article->slug = Article::uniqueSlug($data['title']);
        $article->is_published = $publish;
        $article->published_at = $data['published_at'] ?? now();
        $this->cover($request, $article);
        $article->save();

        return redirect()->route('admin.articles.edit', $article)->with('ok', 'Artikel disimpan.');
    }

    public function edit(Article $article)
    {
        return view('admin.articles.form', compact('article'));
    }

    public function update(Request $request, Article $article)
    {
        [$data, $publish] = $this->validated($request);

        $article->fill($data);
        if ($request->boolean('regenerate_slug') && $article->isDirty('title')) {
            $article->slug = Article::uniqueSlug($data['title'], $article->id);
        }
        $article->is_published = $publish;
        $article->published_at = $data['published_at'] ?? $article->published_at ?? now();
        if ($request->boolean('remove_cover')) {
            Images::delete($article->cover_path, $article->cover_card_path);
            $article->forceFill(['cover_path' => null, 'cover_card_path' => null]);
        }
        $this->cover($request, $article);
        $article->save();

        return back()->with('ok', 'Perubahan disimpan.');
    }

    public function destroy(Article $article)
    {
        Images::delete($article->cover_path, $article->cover_card_path);
        $article->delete();

        return redirect()->route('admin.articles.index')->with('ok', 'Artikel dihapus.');
    }

    private function cover(Request $request, Article $article): void
    {
        if (! $request->hasFile('cover')) {
            return;
        }

        $paths = Images::storeWide($request->file('cover'));
        Images::delete($article->cover_path, $article->cover_card_path);
        $article->cover_path = $paths['path'];
        $article->cover_card_path = $paths['card_path'];
    }

    /** @return array{0: array, 1: bool} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'topic' => ['nullable', 'string', 'max:60'],
            'excerpt' => ['nullable', 'string', 'max:320'],
            'body' => ['required', 'string', 'max:60000'],
            'author' => ['nullable', 'string', 'max:80'],
            'cover_alt' => ['nullable', 'string', 'max:160'],
            'published_at' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'cover' => ['nullable', 'image', 'max:5120'],
        ]);

        unset($data['cover']);
        if (! empty($data['published_at'])) {
            $data['published_at'] = Carbon::parse($data['published_at']);
        }

        return [$data, $request->boolean('is_published')];
    }
}
