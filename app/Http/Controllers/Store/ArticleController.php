<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Support\Seo;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $topic = trim((string) $request->query('topik', ''));

        $articles = Article::published()
            ->when($topic !== '', fn ($q) => $q->where('topic', $topic))
            ->orderByDesc('published_at')->orderByDesc('id')->paginate(9)->withQueryString();

        $trail = [['Beranda', url('/')], ['Artikel', route('articles.index')]];
        $page = $articles->currentPage();

        return view('store.articles.index', [
            'seo' => Seo::page([
                'title' => 'Artikel'.($page > 1 ? " - halaman {$page}" : ''),
                'description' => 'Tips belanja, info produk, dan kabar terbaru dari '.config('store.name').'.',
                'canonical' => route('articles.index').($page > 1 ? '?page='.$page : ''),
                'robots' => $topic !== '' ? 'noindex,follow' : 'index,follow,max-image-preview:large',
                'jsonld' => [Seo::breadcrumbs($trail)],
            ]),
            'articles' => $articles,
            'topics' => Article::published()->whereNotNull('topic')->distinct()->orderBy('topic')->pluck('topic'),
            'topic' => $topic,
            'trail' => $trail,
        ]);
    }

    public function show(Article $article)
    {
        abort_unless($article->is_published && $article->published_at && $article->published_at->lte(now()), 404);

        $related = Article::published()->where('id', '!=', $article->id)
            ->orderByRaw('case when topic = ? then 0 else 1 end', [$article->topic])
            ->orderByDesc('published_at')->limit(3)->get();

        $trail = [['Beranda', url('/')], ['Artikel', route('articles.index')], [$article->title, $article->url()]];

        $jsonld = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article->title,
            'description' => $article->summary(),
            'datePublished' => $article->published_at->toAtomString(),
            'dateModified' => $article->updated_at->toAtomString(),
            'author' => ['@type' => 'Person', 'name' => $article->author ?: config('store.name')],
            'publisher' => ['@type' => 'Organization', 'name' => config('store.name')],
            'mainEntityOfPage' => $article->url(),
        ];
        if ($cover = $article->coverUrl()) {
            $jsonld['image'] = [url($cover)];
        }

        return view('store.articles.show', [
            'seo' => Seo::page([
                'title' => $article->meta_title ?: $article->title,
                'description' => $article->meta_description ?: $article->summary(),
                'canonical' => $article->url(),
                'image' => $article->coverUrl() ? url($article->coverUrl()) : null,
                'type' => 'article',
                'jsonld' => [$jsonld, Seo::breadcrumbs($trail)],
            ]),
            'article' => $article,
            'related' => $related,
            'trail' => $trail,
        ]);
    }
}
