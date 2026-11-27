@extends('layouts.admin')

@section('title', 'Pengumuman')
@section('page_name', 'Kirim Pengumuman ke Penyewa / Pemilik')

@section('content')
@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card-custom mb-4">
    <h6 class="fw-bold mb-3"><i class="bi bi-megaphone"></i> Buat Pengumuman Baru</h6>
    <p class="text-muted small mb-3">Muncul di layar Notifikasi aplikasi mobile penyewa/pemilik (menyatu dengan notifikasi booking/ulasan yang sudah ada).</p>
    <form action="{{ route('admin.broadcasts.store') }}" method="POST" style="max-width: 600px;">
        @csrf
        <div class="mb-3">
            <label class="form-label fw-semibold">Judul</label>
            <input type="text" name="title" class="form-control" placeholder="Mis. Pemeliharaan Server" required maxlength="150">
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Pesan</label>
            <textarea name="message" class="form-control" rows="3" placeholder="Isi pengumuman..." required maxlength="1000"></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Kirim ke</label>
            <select name="target_role" class="form-select">
                <option value="">Semua (Penyewa & Pemilik)</option>
                <option value="user">Penyewa saja</option>
                <option value="owner">Pemilik Kos saja</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary-custom">Kirim Pengumuman</button>
    </form>
</div>

<div class="card-custom">
    <h6 class="fw-bold mb-3">Riwayat Pengumuman</h6>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Judul</th>
                    <th>Pesan</th>
                    <th>Target</th>
                    <th>Dikirim</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($broadcasts as $b)
                    <tr>
                        <td class="fw-semibold">{{ $b->title }}</td>
                        <td class="text-muted" style="max-width: 320px;">{{ \Illuminate\Support\Str::limit($b->message, 80) }}</td>
                        <td>
                            <span class="badge bg-secondary-subtle text-secondary">
                                {{ $b->target_role === 'user' ? 'Penyewa' : ($b->target_role === 'owner' ? 'Pemilik' : 'Semua') }}
                            </span>
                        </td>
                        <td class="text-muted small">{{ $b->created_at->format('d M Y H:i') }} @if($b->creator)oleh {{ $b->creator->name }}@endif</td>
                        <td>
                            <form action="{{ route('admin.broadcasts.destroy', $b->id) }}" method="POST" onsubmit="return confirm('Hapus pengumuman ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    @include('admin.partials.empty-row', ['colspan' => 5, 'icon' => 'bi-megaphone', 'text' => 'Belum ada pengumuman yang dikirim.'])
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $broadcasts->links() }}
</div>
@endsection
