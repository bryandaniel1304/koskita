@extends('layouts.admin')

@section('title', 'Kelola Kos')
@section('page_name', 'Kelola Listing Kos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0 text-secondary">Daftar Kos Terdaftar</h5>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.koses.export') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
            <i class="bi bi-download"></i> Export CSV
        </a>
        <a href="{{ route('admin.koses.create') }}" class="btn btn-primary-custom d-flex align-items-center gap-2">
            <i class="bi bi-plus-lg"></i> Tambah Kos Baru
        </a>
    </div>
</div>

<form method="GET" class="d-flex align-items-center gap-2 mb-3" style="max-width: 320px;">
    <label for="owner_id" class="form-label fw-semibold mb-0 text-nowrap">Pemilik:</label>
    <select name="owner_id" id="owner_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Semua Pemilik</option>
        <option value="none" {{ request('owner_id') === 'none' ? 'selected' : '' }}>Tanpa Pemilik (Admin)</option>
        @foreach($owners as $owner)
            <option value="{{ $owner->id }}" {{ (string) request('owner_id') === (string) $owner->id ? 'selected' : '' }}>{{ $owner->name }}</option>
        @endforeach
    </select>
</form>

<div class="card-custom">
    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="width: 80px;">Gambar</th>
                    <th scope="col">Nama Kos</th>
                    <th scope="col">Pemilik</th>
                    <th scope="col">Harga Sewa</th>
                    <th scope="col">Kamar</th>
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
                            <div class="fw-bold text-dark">
                                {{ $kos->name }}
                                @if($kos->verified_at)
                                    <i class="bi bi-patch-check-fill text-primary" title="Kos Terverifikasi"></i>
                                @endif
                            </div>
                            <small class="text-muted d-block text-truncate" style="max-width: 250px;">{{ $kos->description }}</small>
                        </td>
                        <td>
                            @if($kos->owner)
                                <span class="badge bg-primary-subtle text-primary">{{ $kos->owner->name }}</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary-emphasis">Admin</span>
                            @endif
                        </td>
                        <td>
                            <span class="fw-bold text-indigo">Rp {{ number_format($kos->price) }}</span> <small class="text-muted">/bln</small>
                        </td>
                        <td>
                            <span class="badge {{ $kos->available_rooms > 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                {{ $kos->occupied_rooms }}/{{ $kos->total_rooms }} terisi
                            </span>
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
                                <form action="{{ route('admin.koses.toggle-verified', $kos->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit" class="btn btn-sm {{ $kos->verified_at ? 'btn-primary-custom' : 'btn-outline-secondary' }}" title="{{ $kos->verified_at ? 'Cabut verifikasi' : 'Tandai terverifikasi' }}">
                                        <i class="bi bi-patch-check{{ $kos->verified_at ? '-fill' : '' }}"></i>
                                    </button>
                                </form>
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
                    @include('admin.partials.empty-row', ['colspan' => 9, 'icon' => 'bi-house-x', 'text' => 'Belum ada data kos terdaftar.'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
