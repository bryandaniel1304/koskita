@extends('layouts.admin')

@section('title', 'Detail Responden')
@section('page_name', 'Detail Responden: ' . $targetUser->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ route('admin.users') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Kembali ke Daftar
    </a>
    <form action="{{ route('admin.users.update', $targetUser->id) }}" method="POST" class="d-flex align-items-center gap-2">
        @csrf
        @method('PUT')
        <label class="mb-0 small text-muted">Role:</label>
        <select name="role" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
            <option value="user" {{ $targetUser->role === 'user' ? 'selected' : '' }}>User</option>
            <option value="admin" {{ $targetUser->role === 'admin' ? 'selected' : '' }}>Admin</option>
        </select>
    </form>
</div>

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-4 mb-4">
    <div class="col-md-5">
        <div class="card-custom h-100">
            <h6 class="fw-bold mb-3"><i class="bi bi-person-badge"></i> Profil Preferensi</h6>
            <table class="table table-sm mb-0">
                <tr><td class="text-muted">Nama</td><td class="fw-semibold">{{ $targetUser->name }}</td></tr>
                <tr><td class="text-muted">Email</td><td><code>{{ $targetUser->email }}</code></td></tr>
                <tr><td class="text-muted">Gender</td><td class="text-capitalize">{{ $targetUser->profile->gender ?? '-' }}</td></tr>
                <tr><td class="text-muted">Pekerjaan</td><td class="text-capitalize">{{ $targetUser->profile->occupation ?? '-' }}</td></tr>
                <tr><td class="text-muted">Lokasi Preferensi</td><td>{{ $targetUser->profile->preferred_location ?? '-' }}</td></tr>
                <tr>
                    <td class="text-muted">Budget</td>
                    <td>
                        @if($targetUser->profile)
                            Rp {{ number_format($targetUser->profile->budget_min) }} - Rp {{ number_format($targetUser->profile->budget_max) }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Fasilitas</td>
                    <td>
                        @foreach($targetUser->profile->preferred_facilities ?? [] as $f)
                            <span class="badge bg-secondary-subtle text-secondary">{{ $f }}</span>
                        @endforeach
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Aturan</td>
                    <td>
                        @foreach($targetUser->profile->preferred_rules ?? [] as $r)
                            <span class="badge bg-warning-subtle text-warning">{{ $r }}</span>
                        @endforeach
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card-custom h-100">
            <h6 class="fw-bold mb-3"><i class="bi bi-activity"></i> Riwayat Interaksi</h6>
            <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kos</th>
                            <th>Rating</th>
                            <th>Favorit</th>
                            <th>Klik</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($targetUser->interactions as $i)
                            <tr>
                                <td>{{ $i->kos->name ?? '(kos dihapus)' }}</td>
                                <td>{{ $i->rating ?? '-' }}</td>
                                <td>{!! $i->is_favorite ? '<i class="bi bi-heart-fill text-danger"></i>' : '-' !!}</td>
                                <td>{{ $i->click_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">Belum ada interaksi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card-custom">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-stars"></i> Rekomendasi Saat Ini (Live)</h6>
        <div>
            @if($recommendationResult['is_cold_start'])
                <span class="badge bg-info-subtle text-info">Cold-Start &mdash; Content-Based Murni (α = 1.0)</span>
            @else
                <span class="badge bg-success-subtle text-success">
                    Warm-Start &mdash; Hybrid CB+CF (α = {{ $recommendationResult['alpha'] }})
                </span>
            @endif
            <span class="badge bg-secondary-subtle text-secondary">{{ $recommendationResult['rating_count'] }} rating diberikan</span>
        </div>
    </div>
    <p class="text-muted small mb-3">
        Dihitung real-time berdasarkan kondisi profil &amp; interaksi pengguna saat halaman ini dibuka, memakai algoritma yang sama persis dengan yang dipanggil aplikasi Flutter (<code>RecommendationService::getRecommendations</code>).
    </p>
    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Kos</th>
                    <th>Score CB</th>
                    <th>Score CF</th>
                    <th>Score Hybrid</th>
                    <th>Match %</th>
                    <th>Alasan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recommendationResult['recommendations'] as $idx => $rec)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td class="fw-semibold">{{ $rec['kos']->name }}</td>
                        <td>{{ number_format($rec['score_cb'], 3) }}</td>
                        <td>{{ number_format($rec['score_cf'], 3) }}</td>
                        <td class="fw-bold">{{ number_format($rec['score_hybrid'], 3) }}</td>
                        <td><span class="badge bg-primary">{{ $rec['match_percentage'] }}%</span></td>
                        <td>
                            <ul class="small mb-0 ps-3">
                                @foreach($rec['explanation'] as $reason)
                                    <li>{{ $reason }}</li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">Belum ada kos untuk direkomendasikan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    <form action="{{ route('admin.users.destroy', $targetUser->id) }}" method="POST" onsubmit="return confirm('Yakin hapus pengguna ini beserta seluruh data interaksinya?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-trash"></i> Hapus Pengguna Ini
        </button>
    </form>
</div>
@endsection
