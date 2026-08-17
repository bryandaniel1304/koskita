<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AdminArticleController extends Controller
{
    public function index()
    {
        $articles = Article::latest()->paginate(20);

        return view('admin.articles.index', compact('articles'));
    }

    public function create()
    {
        return view('admin.articles.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $validated['slug'] = $this->uniqueSlug($validated['title']);
        $validated['author_id'] = Auth::id();
        $validated['published_at'] = $request->boolean('publish_now') ? now() : null;

        Article::create($validated);

        return redirect()->route('admin.articles.index')->with('success', 'Artikel berhasil dibuat.');
    }

    public function edit($id)
    {
        $article = Article::findOrFail($id);

        return view('admin.articles.edit', compact('article'));
    }

    public function update(Request $request, $id)
    {
        $article = Article::findOrFail($id);
        $validated = $this->validated($request, $article->id);

        if ($validated['title'] !== $article->title) {
            $validated['slug'] = $this->uniqueSlug($validated['title'], $article->id);
        }

        if ($request->boolean('publish_now') && !$article->published_at) {
            $validated['published_at'] = now();
        } elseif (!$request->boolean('publish_now')) {
            $validated['published_at'] = null;
        }

        $article->update($validated);

        return redirect()->route('admin.articles.index')->with('success', 'Artikel berhasil diperbarui.');
    }

    public function destroy($id)
    {
        Article::findOrFail($id)->delete();

        return redirect()->route('admin.articles.index')->with('success', 'Artikel berhasil dihapus.');
    }

    protected function validated(Request $request, $ignoreId = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'excerpt' => 'required|string|max:300',
            'body' => 'required|string',
            'cover_image_url' => 'nullable|url|max:2048',
        ]);
    }

    protected function uniqueSlug(string $title, $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;
        while (Article::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }
}
