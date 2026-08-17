@extends('layouts.admin')

@section('title', 'Tips Ngekos')
@section('page_name', 'Kelola Artikel "Tips Ngekos"')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Konten SEO -- menyasar kata kunci pencarian nyata (mis. "cara nego kos") sebelum orang tahu KosKita ada.</p>
    <a href="{{ route('admin.articles.create') }}" class="btn btn-primary-custom d-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i> Tulis Artikel Baru
    </a>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Judul</th>
                    <th>Status</th>
                    <th>Ditulis</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($articles as $article)
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $article->title }}</div>
                            <small class="text-muted">/tips/{{ $article->slug }}</small>
                        </td>
                        <td>
                            @if($article->isPublished())
                                <span class="badge bg-success-subtle text-success">Terbit</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">Draf</span>
                            @endif
                        </td>
                        <td><small class="text-muted">{{ $article->created_at->diffForHumans() }}</small></td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-2">
                                @if($article->isPublished())
                                    <a href="{{ route('web.tips.show', $article->slug) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Lihat">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                @endif
                                <a href="{{ route('admin.articles.edit', $article->id) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                                <form action="{{ route('admin.articles.destroy', $article->id) }}" method="POST" onsubmit="return confirm('Hapus artikel ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    @include('admin.partials.empty-row', ['colspan' => 4, 'icon' => 'bi-file-earmark-text', 'text' => 'Belum ada artikel.'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="d-flex justify-content-center mt-3">
    {{ $articles->links('pagination::bootstrap-5') }}
</div>
@endsection
