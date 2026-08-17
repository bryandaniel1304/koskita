@extends('layouts.app')

@section('title', 'Responden')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-1">Data Responden</h4>
    <p class="text-muted mb-0">Daftar akun penyewa (role "user") beserta ringkasan interaksinya -- untuk analisis demografis Bab IV.</p>
</div>

<div class="card-custom p-3">
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Gender</th>
                    <th>Pekerjaan</th>
                    <th>Area</th>
                    <th>Budget</th>
                    <th>Jumlah Interaksi</th>
                    <th>Rating Diberikan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @foreach($respondents as $r)
                @php
                    $ratedCount = $r->interactions->whereNotNull('rating')->count();
                @endphp
                <tr>
                    <td class="fw-semibold">{{ $r->name }}</td>
                    <td>{{ $r->profile ? ucfirst($r->profile->gender) : '—' }}</td>
                    <td>{{ $r->profile ? ucfirst($r->profile->occupation) : '—' }}</td>
                    <td>{{ $r->profile->preferred_location ?? '—' }}</td>
                    <td>
                        @if($r->profile)
                            Rp{{ number_format($r->profile->budget_min / 1000000, 1) }}jt - Rp{{ number_format($r->profile->budget_max / 1000000, 1) }}jt
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $r->interactions->count() }}</td>
                    <td>{{ $ratedCount }}</td>
                    <td>
                        @if($ratedCount > 0)
                            <span class="badge bg-success-subtle text-success">Warm-Start</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Cold-Start</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $respondents->links() }}</div>
</div>
@endsection
