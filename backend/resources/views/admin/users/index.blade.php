@extends('layouts.admin')

@section('title', 'Data Responden')
@section('page_name', 'Daftar Responden & Profil Preferensi')

@section('content')
<div class="card-custom">
    <form method="GET" class="d-flex gap-2 mb-3" style="max-width: 400px;">
        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Cari nama atau email...">
        <button type="submit" class="btn btn-primary-custom"><i class="bi bi-search"></i></button>
    </form>
    <div class="table-responsive">
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
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Belum ada data responden.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="d-flex justify-content-center">
    {{ $users->links('pagination::bootstrap-5') }}
</div>
@endsection
