@extends('layouts.admin')

@section('title', 'Fasilitas & Aturan')
@section('page_name', 'Kelola Aturan')

@section('content')
<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.facilities.index') }}">Fasilitas</a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="{{ route('admin.rules.index') }}">Aturan</a>
    </li>
</ul>

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card-custom">
    <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle"></i> Tambah Aturan Baru</h6>
    <form action="{{ route('admin.rules.store') }}" method="POST" class="d-flex gap-2 mb-4" style="max-width: 500px;">
        @csrf
        <input type="text" name="name" class="form-control" placeholder="Nama aturan (mis. Dilarang Membawa Kendaraan)" required>
        <button type="submit" class="btn btn-primary-custom">Tambah</button>
    </form>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nama Aturan</th>
                    <th>Dipakai di Kos</th>
                    <th style="width: 320px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rules as $r)
                    <tr>
                        <td class="w-50">
                            <form action="{{ route('admin.rules.update', $r->id) }}" method="POST" class="d-flex align-items-center gap-2" id="edit-rule-{{ $r->id }}">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" value="{{ $r->name }}" class="form-control form-control-sm">
                            </form>
                        </td>
                        <td><span class="badge bg-secondary-subtle text-secondary">{{ $r->koses_count }} kos</span></td>
                        <td class="d-flex gap-2">
                            <button type="submit" form="edit-rule-{{ $r->id }}" class="btn btn-sm btn-outline-primary">Simpan</button>
                            <form action="{{ route('admin.rules.destroy', $r->id) }}" method="POST" onsubmit="return confirm('Hapus aturan {{ $r->name }}? Aturan ini akan hilang dari semua kos yang memakainya.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    @include('admin.partials.empty-row', ['colspan' => 3, 'icon' => 'bi-clipboard-x', 'text' => 'Belum ada aturan.'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
