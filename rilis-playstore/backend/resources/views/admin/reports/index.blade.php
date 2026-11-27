@extends('layouts.admin')

@section('title', 'Laporan')
@section('page_name', 'Laporan Pengguna')

@section('content')
@php
    $statusLabels = ['pending' => 'Menunggu', 'reviewed' => 'Ditinjau', 'dismissed' => 'Ditolak'];
    $statusColors = ['pending' => 'warning', 'reviewed' => 'success', 'dismissed' => 'secondary'];
@endphp

<div class="card-custom">
    <div class="d-flex gap-2 mb-3">
        <a href="{{ route('admin.reports.index', ['status' => 'pending']) }}" class="btn btn-sm {{ $status === 'pending' ? 'btn-primary-custom' : 'btn-outline-secondary' }}">Menunggu</a>
        <a href="{{ route('admin.reports.index', ['status' => 'reviewed']) }}" class="btn btn-sm {{ $status === 'reviewed' ? 'btn-primary-custom' : 'btn-outline-secondary' }}">Ditinjau</a>
        <a href="{{ route('admin.reports.index', ['status' => 'dismissed']) }}" class="btn btn-sm {{ $status === 'dismissed' ? 'btn-primary-custom' : 'btn-outline-secondary' }}">Ditolak</a>
        <a href="{{ route('admin.reports.index', ['status' => 'all']) }}" class="btn btn-sm {{ $status === 'all' ? 'btn-primary-custom' : 'btn-outline-secondary' }}">Semua</a>
    </div>

    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Jenis</th>
                    <th>Objek Dilaporkan</th>
                    <th>Alasan</th>
                    <th>Pelapor</th>
                    <th>Status</th>
                    <th>Waktu</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $r)
                    <tr>
                        <td>
                            @if($r->reportable_type === \App\Models\Kos::class)
                                <span class="badge bg-info-subtle text-info"><i class="bi bi-house"></i> Kos</span>
                            @else
                                <span class="badge bg-primary-subtle text-primary"><i class="bi bi-chat-square-text"></i> Ulasan</span>
                            @endif
                        </td>
                        <td>
                            @if($r->target)
                                @if($r->reportable_type === \App\Models\Kos::class)
                                    <a href="{{ route('admin.koses.edit', $r->target->id) }}">{{ $r->target->name }}</a>
                                @else
                                    <span class="small text-muted">"{{ \Illuminate\Support\Str::limit($r->target->comment, 60) }}"</span>
                                @endif
                            @else
                                <span class="text-muted fst-italic">Data sudah dihapus</span>
                            @endif
                        </td>
                        <td class="small">{{ $r->reason }}@if($r->details)<br><span class="text-muted">{{ \Illuminate\Support\Str::limit($r->details, 80) }}</span>@endif</td>
                        <td>{{ $r->reporter->name ?? '-' }}</td>
                        <td><span class="badge bg-{{ $statusColors[$r->status] }}-subtle text-{{ $statusColors[$r->status] }}">{{ $statusLabels[$r->status] }}</span></td>
                        <td><small class="text-muted">{{ $r->created_at->diffForHumans() }}</small></td>
                        <td class="text-end">
                            @if($r->status === 'pending')
                                <div class="d-flex gap-1 justify-content-end">
                                    <form action="{{ route('admin.reports.update', $r->id) }}" method="POST">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="dismissed">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Tolak</button>
                                    </form>
                                    <form action="{{ route('admin.reports.update', $r->id) }}" method="POST">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="reviewed">
                                        <button type="submit" class="btn btn-sm btn-primary-custom">Tindak Lanjuti</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    @include('admin.partials.empty-row', ['colspan' => 7, 'icon' => 'bi-flag', 'text' => 'Tidak ada laporan.'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="d-flex justify-content-center">
    {{ $reports->links('pagination::bootstrap-5') }}
</div>
@endsection
