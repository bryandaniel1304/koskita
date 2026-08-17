<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Article;

class WebTipsController extends Controller
{
    public function index()
    {
        $articles = Article::published()->latest('published_at')->paginate(9);

        return view('web.tips.index', compact('articles'));
    }

    public function show(string $slug)
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();

        $related = Article::published()
            ->where('id', '!=', $article->id)
            ->latest('published_at')
            ->take(3)
            ->get();

        return view('web.tips.show', compact('article', 'related'));
    }
}
