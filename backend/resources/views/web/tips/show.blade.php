@extends('web.layouts.app')

@section('title', $article->title)
@section('meta_description', $article->excerpt)
@section('og_type', 'article')
@if($article->cover_image_url)
    @section('og_image', $article->cover_image_url)
@endif

@section('content')
<div class="container py-4">
    <nav class="small text-muted mb-3">
        <a href="{{ route('web.home') }}" class="text-muted">Beranda</a> /
        <a href="{{ route('web.tips.index') }}" class="text-muted">Tips Ngekos</a> /
        <span>{{ $article->title }}</span>
    </nav>

    <div class="row g-4">
        <div class="col-lg-8">
            <article class="card-koskita p-4 p-md-5">
                @if($article->cover_image_url)
                    <img src="{{ $article->cover_image_url }}" class="w-100 rounded-3 mb-4" style="max-height: 320px; object-fit: cover;" alt="{{ $article->title }}">
                @endif
                <h1 class="fw-bold mb-2" style="font-size: 1.9rem;">{{ $article->title }}</h1>
                <p class="text-muted small mb-4">
                    {{ $article->published_at->translatedFormat('d F Y') }}
                    @if($article->author)
                        &middot; oleh {{ $article->author->name }}
                    @endif
                </p>
                {{-- Escaped + white-space:pre-line (bukan nl2br mentah) -- pola
                     yang sama dengan deskripsi kos di halaman ini juga, supaya
                     isi artikel tidak bisa jadi vektor stored-XSS kalau suatu
                     saat akun admin lain ikut menulis. --}}
                <div style="font-size: 1.02rem; line-height: 1.8; white-space: pre-line;">{{ $article->body }}</div>
            </article>
        </div>

        <div class="col-lg-4">
            @if($related->isNotEmpty())
                <div class="card-koskita p-4 sticky-top" style="top: 90px;">
                    <h6 class="fw-bold mb-3">Baca Juga</h6>
                    @foreach($related as $r)
                        <a href="{{ route('web.tips.show', $r->slug) }}" class="d-block text-decoration-none text-dark py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <p class="fw-semibold mb-1 small">{{ $r->title }}</p>
                            <p class="text-muted mb-0" style="font-size: 0.78rem;">{{ $r->published_at->translatedFormat('d M Y') }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
