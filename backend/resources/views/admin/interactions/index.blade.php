@extends('layouts.admin')

@section('title', 'Log Interaksi')
@section('page_name', 'Log Aktivitas & Interaksi Pengguna')

@section('content')
<div class="card-custom">
    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Responden</th>
                    <th scope="col">Kos Tujuan</th>
                    <th scope="col" class="text-center">Jumlah Clicks</th>
                    <th scope="col" class="text-center">Favorit</th>
                    <th scope="col" class="text-center">Rating</th>
                    <th scope="col">Waktu Aktivitas</th>
                </tr>
            </thead>
            <tbody>
                @forelse($interactions as $inter)
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $inter->user->name ?? 'User Terhapus' }}</div>
                            <small class="text-muted">{{ $inter->user->email ?? '' }}</small>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $inter->kos->name ?? 'Kos Terhapus' }}</div>
                            <small class="text-muted">{{ $inter->kos->location ?? '' }}</small>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary rounded-pill px-3">{{ $inter->click_count }}</span>
                        </td>
                        <td class="text-center">
                            @if($inter->is_favorite)
                                <span class="text-danger"><i class="bi bi-heart-fill"></i></span>
                            @else
                                <span class="text-muted"><i class="bi bi-heart"></i></span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($inter->rating)
                                <div class="text-warning">
                                    @for($i = 1; $i <= 5; $i++)
                                        <i class="bi {{ $i <= $inter->rating ? 'bi-star-fill' : 'bi-star' }}"></i>
                                    @endfor
                                </div>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            <small class="text-muted">{{ $inter->updated_at->diffForHumans() }}</small>
                        </td>
                    </tr>
                @empty
                    @include('admin.partials.empty-row', ['colspan' => 6, 'icon' => 'bi-activity', 'text' => 'Belum ada log interaksi.'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
