@extends('layouts.admin')

@section('title', $role === 'owner' ? 'Data Pemilik Kos' : 'Data Responden')
@section('page_name', $role === 'owner' ? 'Daftar Pemilik Kos & Verifikasi' : 'Daftar Responden & Profil Preferensi')

@section('content')
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link {{ $role === 'user' ? 'active fw-bold text-primary' : 'text-secondary' }}" href="{{ route('admin.users', ['role' => 'user', 'search' => $search]) }}">
            <i class="bi bi-people-fill"></i> Penyewa Kos (Responden)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $role === 'owner' ? 'active fw-bold text-primary' : 'text-secondary' }}" href="{{ route('admin.users', ['role' => 'owner', 'search' => $search]) }}">
            <i class="bi bi-shop"></i> Pemilik Kos (Penyedia)
        </a>
    </li>
</ul>

<div class="card-custom">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <form method="GET" class="d-flex gap-2" style="max-width: 400px;">
            <input type="hidden" name="role" value="{{ $role }}">
            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Cari nama atau email...">
            <button type="submit" class="btn btn-primary-custom"><i class="bi bi-search"></i></button>
        </form>
        <a href="{{ route('admin.users.export', ['role' => $role]) }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
            <i class="bi bi-download"></i> Export CSV
        </a>
    </div>
    <div class="table-responsive">
        @if($role === 'owner')
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Nama Pemilik</th>
                        <th scope="col">Email</th>
                        <th scope="col">Nomor HP</th>
                        <th scope="col">Status Verifikasi</th>
                        <th scope="col">Jumlah Kos</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $u)
                        <tr class="cursor-pointer" onclick="window.location='{{ route('admin.users.show', $u->id) }}'" style="cursor:pointer;">
                            <td>
                                <div class="fw-bold text-dark">{{ $u->name }}</div>
                            </td>
                            <td>
                                <code>{{ $u->email }}</code>
                            </td>
                            <td>
                                {{ $u->phone ?? '-' }}
                            </td>
                            <td>
                                @php
                                    $verifStatusLabel = ['none' => 'Belum Mengajukan', 'pending' => 'Menunggu Peninjauan', 'approved' => 'Terverifikasi', 'rejected' => 'Ditolak'];
                                    $verifStatusColor = ['none' => 'secondary', 'pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
                                @endphp
                                <span class="badge bg-{{ $verifStatusColor[$u->owner_verification_status] ?? 'secondary' }}-subtle text-{{ $verifStatusColor[$u->owner_verification_status] ?? 'secondary' }}">
                                    {{ $verifStatusLabel[$u->owner_verification_status] ?? $u->owner_verification_status }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold text-dark">{{ $u->koses->count() }} kos</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.users.show', $u->id) }}" class="btn btn-sm btn-outline-primary">
                                    Detail &amp; Verifikasi <i class="bi bi-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        @include('admin.partials.empty-row', ['colspan' => 6, 'icon' => 'bi-shop', 'text' => 'Belum ada data pemilik kos.'])
                    @endforelse
                </tbody>
            </table>
        @else
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Nama Responden</th>
                        <th scope="col">Email</th>
                        <th scope="col">Gender</th>
                        <th scope="col">Pekerjaan</th>
                        <th scope="col">Area Preferensi</th>
                        <th scope="col">Rentang Budget</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $u)
                        <tr class="cursor-pointer" onclick="window.location='{{ route('admin.users.show', $u->id) }}'" style="cursor:pointer;">
                            <td>
                                <div class="fw-bold text-dark">{{ $u->name }}</div>
                            </td>
                            <td>
                                <code>{{ $u->email }}</code>
                            </td>
                            <td>
                                <span class="text-capitalize">{{ $u->profile->gender ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="text-capitalize">{{ $u->profile->occupation ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="fw-semibold text-primary">{{ $u->profile->preferred_location ?? '-' }}</span>
                            </td>
                            <td>
                                @if(isset($u->profile->budget_min) && isset($u->profile->budget_max))
                                    <small class="fw-semibold text-dark">
                                        Rp {{ number_format($u->profile->budget_min / 1000000, 1) }} jt - Rp {{ number_format($u->profile->budget_max / 1000000, 1) }} jt
                                    </small>
                                @else
                                    -
                                    @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.users.show', $u->id) }}" class="btn btn-sm btn-outline-primary">
                                    Detail &amp; Rekomendasi <i class="bi bi-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        @include('admin.partials.empty-row', ['colspan' => 7, 'icon' => 'bi-people', 'text' => 'Belum ada data responden.'])
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>
</div>
<div class="d-flex justify-content-center mt-3">
    {{ $users->links('pagination::bootstrap-5') }}
</div>
@endsection
