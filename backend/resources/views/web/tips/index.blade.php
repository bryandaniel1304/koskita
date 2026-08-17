@extends('web.layouts.app')

@section('title', 'Tips Ngekos')
@section('meta_description', 'Panduan & tips seputar kos untuk mahasiswa -- cara nego harga, checklist pindahan, tips memilih kos yang aman dan nyaman.')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <h1 class="fw-bold" style="font-size: 1.85rem;">Tips Ngekos</h1>
        <p class="text-muted mb-0">Panduan seputar kos untuk mahasiswa -- dari cara memilih sampai tips hidup nyaman di kos.</p>
    </div>

    @if($articles->isEmpty())
        <div class="card-koskita p-5 text-center">
            <i class="bi bi-journal-text fs-1 text-muted mb-3"></i>
            <h6 class="fw-bold">Belum ada artikel</h6>
            <p class="text-muted small mb-0">Nantikan tips seputar kos di sini.</p>
        </div>
    @else
        <div class="row g-4">
            @foreach($articles as $article)
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('web.tips.show', $article->slug) }}" class="text-decoration-none text-dark">
                        <div class="card-koskita h-100 overflow-hidden">
                            @if($article->cover_image_url)
                                <img src="{{ $article->cover_image_url }}" class="w-100" style="height: 160px; object-fit: cover;" alt="{{ $article->title }}">
                            @else
                                <div class="w-100 d-flex align-items-center justify-content-center" style="height: 160px; background: var(--bg-light);">
                                    <i class="bi bi-journal-text fs-1 text-muted"></i>
                                </div>
                            @endif
                            <div class="p-3">
                                <p class="fw-bold mb-1">{{ $article->title }}</p>
                                <p class="text-muted small mb-2">{{ $article->excerpt }}</p>
                                <p class="text-muted mb-0" style="font-size: 0.78rem;">{{ $article->published_at->translatedFormat('d M Y') }}</p>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $articles->links() }}</div>
    @endif
</div>
@endsection
