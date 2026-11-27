@extends('web.layouts.app')

@section('title', 'Tanam Widget KosKita')
@section('meta_description', 'Tanam widget pencarian kos KosKita di situs kampus atau blog kamu -- cukup satu baris kode iframe.')

@section('content')
<div class="container py-4" style="max-width: 760px;">
    <div class="mb-4">
        <h1 class="fw-bold" style="font-size: 1.85rem;">Tanam Widget Pencarian KosKita</h1>
        <p class="text-muted mb-0">
            Punya situs kampus atau blog seputar area kos? Tanam kotak pencarian KosKita langsung di halaman kamu --
            calon penyewa bisa cari kos tanpa harus tahu KosKita duluan.
        </p>
    </div>

    <div class="card-koskita p-4 mb-4">
        <h6 class="fw-bold mb-2">1. Salin kode ini</h6>
        <div class="position-relative">
            <pre id="embedCode" class="p-3 rounded-3 small mb-0" style="background: var(--surface-dark); color: #E2E8F0; overflow-x: auto;"><code>&lt;iframe src="{{ route('web.widget.search') }}" width="100%" height="360" style="border:1px solid #E2E8F0;border-radius:12px;" loading="lazy" title="Cari Kos - KosKita"&gt;&lt;/iframe&gt;</code></pre>
            <button type="button" class="btn btn-sm btn-primary-koskita position-absolute top-0 end-0 m-2" onclick="copyEmbedCode()">Salin</button>
        </div>
    </div>

    <div class="card-koskita p-4 mb-4">
        <h6 class="fw-bold mb-2">2. Tempel di HTML situs kamu</h6>
        <p class="text-muted small mb-0">Taruh kode di atas di mana saja lewat editor HTML situsmu (mis. widget/embed CMS, atau langsung di file HTML). Widget akan menampilkan pencarian singkat + beberapa kos unggulan -- hasil klik akan membuka situs KosKita penuh di tab yang sama.</p>
    </div>

    <div class="card-koskita p-4">
        <h6 class="fw-bold mb-3">Pratinjau Langsung</h6>
        <iframe src="{{ route('web.widget.search') }}" width="100%" height="360" style="border:1px solid #E2E8F0;border-radius:12px;" loading="lazy" title="Pratinjau Widget KosKita"></iframe>
    </div>
</div>

@push('scripts')
<script>
    function copyEmbedCode() {
        const code = document.getElementById('embedCode').innerText;
        navigator.clipboard.writeText(code).then(() => {
            alert('Kode berhasil disalin!');
        });
    }
</script>
@endpush
@endsection
