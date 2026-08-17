@extends('layouts.admin')

@section('title', 'Tulis Artikel')
@section('page_name', 'Tulis Artikel Baru')

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.articles.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="card-custom" style="max-width: 760px;">
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.articles.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label class="form-label fw-semibold">Judul</label>
            <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Ringkasan (maks 300 karakter, dipakai juga sebagai meta description)</label>
            <textarea name="excerpt" rows="2" maxlength="300" class="form-control" required>{{ old('excerpt') }}</textarea>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">URL Gambar Sampul (opsional)</label>
            <input type="url" name="cover_image_url" class="form-control" placeholder="https://..." value="{{ old('cover_image_url') }}">
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Isi Artikel</label>
            <textarea name="body" rows="14" class="form-control" required>{{ old('body') }}</textarea>
            <small class="text-muted">Teks polos saja (bukan HTML) -- baris baru otomatis jadi paragraf baru saat ditampilkan.</small>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="publish_now" value="1" id="publishNow" checked>
            <label class="form-check-label" for="publishNow">Terbitkan sekarang (kalau tidak dicentang, disimpan sebagai draf)</label>
        </div>
        <button type="submit" class="btn btn-primary-custom">Simpan Artikel</button>
    </form>
</div>
@endsection
