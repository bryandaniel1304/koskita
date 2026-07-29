@extends('layouts.admin')

@section('title', 'Kelola Kos')
@section('page_name', 'Kelola Listing Kos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0 text-secondary">Daftar Kos Terdaftar</h5>
    <a href="{{ route('admin.koses.create') }}" class="btn btn-primary-custom d-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i> Tambah Kos Baru
    </a>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="width: 80px;">Gambar</th>
                    <th scope="col">Nama Kos</th>
                    <th scope="col">Harga Sewa</th>
                    <th scope="col">Tipe</th>
                    <th scope="col">Lokasi / Jarak</th>
                    <th scope="col">Fasilitas / Aturan</th>
                    <th scope="col" class="text-end" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($koses as $kos)
                    <tr>
                        <td>
                            <img src="{{ $kos->cover_image }}" alt="{{ $kos->name }}" class="rounded-3" style="width: 60px; height: 60px; object-fit: cover;">
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ $kos->name }}</div>
                            <small class="text-muted d-block text-truncate" style="max-width: 250px;">{{ $kos->description }}</small>
                        </td>
                        <td>
                            <span class="fw-bold text-indigo">Rp {{ number_format($kos->price) }}</span> <small class="text-muted">/bln</small>
                        </td>
                        <td>
                            <span class="badge 
                                @if($kos->gender_type === 'putra') bg-primary-subtle text-primary
                                @elseif($kos->gender_type === 'putri') bg-danger-subtle text-danger
                                @else bg-success-subtle text-success @endif text-capitalize">
                                {{ $kos->gender_type }}
                            </span>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $kos->location }}</div>
                            <small class="text-muted"><i class="bi bi-directions-walk"></i> {{ $kos->distance_to_campus }} km ke UPH</small>
                        </td>
                        <td>
                            <div class="mb-1">
                                @foreach($kos->facilities as $fac)
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis small">{{ $fac->name }}</span>
                                @endforeach
                            </div>
                            <div>
                                @foreach($kos->rules as $rule)
                                    <span class="badge bg-warning-subtle text-warning-emphasis small">{{ $rule->name }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('admin.koses.edit', $kos->id) }}" class="btn btn-sm btn-outline-primary" title="Edit Data">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                                <form action="{{ route('admin.koses.destroy', $kos->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data kos ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Data">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Belum ada data kos terdaftar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
