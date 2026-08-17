@extends('web.layouts.app')

@section('title', 'Analitik -- Portal Pemilik')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h4 class="fw-bold mb-0">Analitik</h4>
        <a href="{{ route('web.owner.dashboard') }}" class="btn btn-outline-koskita btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card-koskita p-4 h-100">
                <h6 class="fw-bold mb-1">Corong Konversi</h6>
                <p class="text-muted small mb-3">Dari dilihat sampai booking dikonfirmasi -- lihat di tahap mana calon penyewa paling sering "hilang".</p>
                <canvas id="funnelChart" height="220"></canvas>
                <div class="row text-center mt-3 g-2">
                    <div class="col-3">
                        <p class="fw-bold mb-0">{{ $funnel['views'] }}</p>
                        <p class="text-muted small mb-0">Dilihat</p>
                    </div>
                    <div class="col-3">
                        <p class="fw-bold mb-0">{{ $funnel['favorites'] }}</p>
                        <p class="text-muted small mb-0">Difavoritkan</p>
                    </div>
                    <div class="col-3">
                        <p class="fw-bold mb-0">{{ $funnel['bookings_submitted'] }}</p>
                        <p class="text-muted small mb-0">Diajukan</p>
                    </div>
                    <div class="col-3">
                        <p class="fw-bold mb-0">{{ $funnel['bookings_confirmed'] }}</p>
                        <p class="text-muted small mb-0">Dikonfirmasi</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card-koskita p-4 h-100">
                <h6 class="fw-bold mb-1">Timeline Okupansi</h6>
                <p class="text-muted small mb-3">Jumlah booking aktif (dikonfirmasi/selesai) yang berlangsung tiap bulan, 6 bulan terakhir.</p>
                <canvas id="occupancyChart" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('funnelChart'), {
        type: 'bar',
        data: {
            labels: ['Dilihat', 'Difavoritkan', 'Diajukan', 'Dikonfirmasi'],
            datasets: [{
                data: [{{ $funnel['views'] }}, {{ $funnel['favorites'] }}, {{ $funnel['bookings_submitted'] }}, {{ $funnel['bookings_confirmed'] }}],
                backgroundColor: ['#355DDB', '#5B7CE8', '#8CA3F0', '#22C55E'],
                borderRadius: 6,
            }]
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    new Chart(document.getElementById('occupancyChart'), {
        type: 'line',
        data: {
            labels: [@foreach($months as $m) '{{ $m['label'] }}', @endforeach],
            datasets: [{
                label: 'Booking Aktif',
                data: [@foreach($months as $m) {{ $m['count'] }}, @endforeach],
                borderColor: '#355DDB',
                backgroundColor: 'rgba(53,93,219,0.1)',
                fill: true,
                tension: 0.3,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
</script>
@endsection
