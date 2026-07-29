@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_name', 'Dashboard Ringkasan')

@section('content')
<div class="row">
    <!-- Stat 1: Total Users -->
    <div class="col-md-3">
        <div class="card-custom d-flex align-items-center gap-3">
            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3">
                <i class="bi bi-people-fill fs-3"></i>
            </div>
            <div>
                <p class="text-muted mb-0 small">Total Responden</p>
                <h3 class="mb-0 fw-bold text-dark">{{ $totalUsers }}</h3>
            </div>
        </div>
    </div>

    <!-- Stat 2: Total Kos -->
    <div class="col-md-3">
        <div class="card-custom d-flex align-items-center gap-3">
            <div class="p-3 bg-success bg-opacity-10 text-success rounded-3">
                <i class="bi bi-houses-fill fs-3"></i>
            </div>
            <div>
                <p class="text-muted mb-0 small">Kos Terdaftar</p>
                <h3 class="mb-0 fw-bold text-dark">{{ $totalKoses }}</h3>
            </div>
        </div>
    </div>

    <!-- Stat 3: Total Interactions -->
    <div class="col-md-3">
        <div class="card-custom d-flex align-items-center gap-3">
            <div class="p-3 bg-info bg-opacity-10 text-info rounded-3">
                <i class="bi bi-activity fs-3"></i>
            </div>
            <div>
                <p class="text-muted mb-0 small">Total Interaksi</p>
                <h3 class="mb-0 fw-bold text-dark">{{ $totalInteractions }}</h3>
            </div>
        </div>
    </div>

    <!-- Stat 4: Average Rating -->
    <div class="col-md-3">
        <div class="card-custom d-flex align-items-center gap-3">
            <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-3">
                <i class="bi bi-star-fill fs-3"></i>
            </div>
            <div>
                <p class="text-muted mb-0 small">Rata-rata Rating</p>
                <h3 class="mb-0 fw-bold text-dark">{{ number_format($avgRating, 1) }}</h3>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-3">
        <a href="{{ route('admin.bookings.index', ['status' => 'pending']) }}" class="text-decoration-none">
            <div class="card-custom d-flex align-items-center gap-3">
                <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-3">
                    <i class="bi bi-calendar-exclamation fs-3"></i>
                </div>
                <div>
                    <p class="text-muted mb-0 small">Booking Menunggu</p>
                    <h3 class="mb-0 fw-bold text-dark">{{ $pendingBookings }}</h3>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card-custom h-100">
            <h6 class="fw-bold mb-3">Status Rekomendasi Pengguna</h6>
            <canvas id="coldWarmChart" height="180"></canvas>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-custom h-100">
            <h6 class="fw-bold mb-3">Status Booking</h6>
            <canvas id="bookingStatusChart" height="180"></canvas>
        </div>
    </div>
</div>

<div class="card-custom mt-4">
    <h5 class="fw-bold mb-3">Selamat Datang di Panel Admin KOSKITA</h5>
    <p class="text-muted mb-0">
        Gunakan menu di samping untuk mengelola listing kos, melihat data responden/pengguna, memantau booking, mengelola master data fasilitas &amp; aturan, dan menelusuri alur rekomendasi yang diterima setiap pengguna secara langsung dari halaman Data Responden.
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('coldWarmChart'), {
        type: 'doughnut',
        data: {
            labels: ['Warm-Start (sudah rating)', 'Cold-Start (belum rating)'],
            datasets: [{
                data: [{{ $warmStartUsers }}, {{ $coldStartUsers }}],
                backgroundColor: ['#22C55E', '#94A3B8'],
            }]
        },
        options: { plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('bookingStatusChart'), {
        type: 'bar',
        data: {
            labels: ['Menunggu', 'Dikonfirmasi', 'Ditolak', 'Dibatalkan', 'Selesai'],
            datasets: [{
                label: 'Jumlah Booking',
                data: [
                    {{ $bookingStatusCounts->get('pending', 0) }},
                    {{ $bookingStatusCounts->get('confirmed', 0) }},
                    {{ $bookingStatusCounts->get('rejected', 0) }},
                    {{ $bookingStatusCounts->get('cancelled', 0) }},
                    {{ $bookingStatusCounts->get('completed', 0) }},
                ],
                backgroundColor: '#6366F1',
            }]
        },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });
</script>
@endsection
