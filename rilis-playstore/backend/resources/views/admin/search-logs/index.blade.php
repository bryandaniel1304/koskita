@extends('layouts.admin')

@section('title', 'Pencarian Nihil')
@section('page_name', 'Permintaan Belum Terpenuhi')

@section('content')
<p class="text-muted small mb-4">Pencarian kos yang tidak menemukan hasil sama sekali -- membantu lihat permintaan (lokasi/budget/fasilitas) yang belum ada penawarannya di KosKita.</p>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card-custom h-100">
            <h6 class="fw-bold mb-3"><i class="bi bi-search"></i> Kata Kunci Paling Sering Nihil</h6>
            @forelse($topKeywords as $row)
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="small">"{{ $row->keyword }}"</span>
                    <span class="badge bg-danger-subtle text-danger">{{ $row->total }}x</span>
                </div>
            @empty
                <p class="text-muted small mb-0">Belum ada data.</p>
            @endforelse
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-custom h-100">
            <h6 class="fw-bold mb-3"><i class="bi bi-geo-alt"></i> Lokasi Paling Sering Nihil</h6>
            @forelse($topLocations as $row)
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="small">{{ $row->location }}</span>
                    <span class="badge bg-danger-subtle text-danger">{{ $row->total }}x</span>
                </div>
            @empty
                <p class="text-muted small mb-0">Belum ada data.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="card-custom">
    <h6 class="fw-bold mb-3">Riwayat Pencarian Nihil</h6>
    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Kata Kunci</th>
                    <th>Lokasi</th>
                    <th>Tipe</th>
                    <th>Budget</th>
                    <th>Pencari</th>
                    <th>Waktu</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recent as $log)
                    <tr>
                        <td class="small">{{ $log->keyword ?: '-' }}</td>
                        <td class="small">{{ $log->location ?: '-' }}</td>
                        <td class="small text-uppercase">{{ $log->gender_type ?: '-' }}</td>
                        <td class="small">
                            @if($log->budget_min || $log->budget_max)
                                Rp {{ number_format($log->budget_min ?? 0, 0, ',', '.') }} -- Rp {{ number_format($log->budget_max ?? 0, 0, ',', '.') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="small">{{ $log->user->name ?? 'Tamu' }}</td>
                        <td class="small text-muted">{{ $log->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    @include('admin.partials.empty-row', ['colspan' => 6, 'icon' => 'bi-search', 'text' => 'Belum ada pencarian yang nihil hasil.'])
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $recent->links() }}</div>
</div>
@endsection
