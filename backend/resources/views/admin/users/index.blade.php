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
        <div class="d-flex gap-2">
            @if($role === 'user')
                @if($editMode)
                    <a href="{{ route('admin.users', ['role' => 'user', 'search' => $search, 'page' => $users->currentPage()]) }}" class="btn btn-primary d-flex align-items-center gap-2">
                        <i class="bi bi-check2"></i> Selesai Edit
                    </a>
                @else
                    <a href="{{ route('admin.users', ['role' => 'user', 'search' => $search, 'page' => $users->currentPage(), 'mode' => 'edit']) }}" class="btn btn-outline-primary d-flex align-items-center gap-2">
                        <i class="bi bi-table"></i> Edit Tabel
                    </a>
                @endif
            @endif
            <a href="{{ route('admin.users.export', ['role' => $role]) }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-download"></i> Export CSV
            </a>
        </div>
    </div>
    @if($editMode)
        <div class="alert alert-info small py-2 d-flex align-items-center gap-2">
            <i class="bi bi-info-circle"></i>
            <span>Klik sel lalu ubah isinya — perubahan langsung tersimpan saat pindah sel atau tekan Enter. Sel hijau = tersimpan, merah = belum tersimpan (arahkan kursor untuk lihat alasannya).</span>
        </div>
        <div id="sheet-status" class="small mb-2" style="min-height: 1.25rem;" aria-live="polite"></div>
    @endif
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
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.users.edit', $u->id) }}" class="btn btn-sm btn-outline-secondary" title="Ubah data" onclick="event.stopPropagation()">
                                    <i class="bi bi-pencil"></i> Ubah
                                </a>
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
        @elseif($editMode)
            <style>
                .sheet-table { border-collapse: collapse; }
                .sheet-table th, .sheet-table td { border: 1px solid #dee2e6; padding: 0; vertical-align: middle; }
                .sheet-table th { padding: .5rem .6rem; font-size: .8rem; white-space: nowrap; }
                .sheet-table td.sheet-static { padding: .35rem .6rem; font-size: .8rem; color: #6c757d; }
                .sheet-cell { border: 0; border-radius: 0; width: 100%; min-width: 110px; padding: .45rem .6rem; font-size: .85rem; background: transparent; transition: background-color .3s; }
                .sheet-cell:focus { outline: 2px solid var(--bs-primary); outline-offset: -2px; box-shadow: none; background: #fff; }
                .sheet-cell.is-saving { background: #fff8e1; }
                .sheet-cell.is-saved { background: #e6f4ea; }
                .sheet-cell.is-error { background: #fdecea; outline: 2px solid var(--bs-danger); outline-offset: -2px; }
                .sheet-cell[type=number] { min-width: 130px; text-align: right; }
            </style>
            <table class="table sheet-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nama Responden</th>
                        <th>Email</th>
                        <th>Gender</th>
                        <th>Pekerjaan</th>
                        <th>Area Preferensi</th>
                        <th>Budget Min (Rp)</th>
                        <th>Budget Maks (Rp)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $u)
                        @php($p = $u->profile)
                        @php($url = route('admin.users.update-cell', $u->id))
                        <tr>
                            <td class="sheet-static text-center">{{ $users->firstItem() + $loop->index }}</td>
                            <td><input class="sheet-cell form-control" data-url="{{ $url }}" data-field="name" value="{{ $u->name }}" style="min-width: 180px;" aria-label="Nama {{ $u->name }}"></td>
                            <td><input class="sheet-cell form-control" data-url="{{ $url }}" data-field="email" type="email" value="{{ $u->email }}" style="min-width: 200px;" aria-label="Email {{ $u->name }}"></td>
                            <td>
                                <select class="sheet-cell form-select" data-url="{{ $url }}" data-field="gender" aria-label="Gender {{ $u->name }}">
                                    @unless($p?->gender)<option value="" selected>-</option>@endunless
                                    @foreach(['pria' => 'Pria', 'wanita' => 'Wanita'] as $val => $label)
                                        <option value="{{ $val }}" @selected($p?->gender === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="sheet-cell form-select" data-url="{{ $url }}" data-field="occupation" aria-label="Pekerjaan {{ $u->name }}">
                                    @unless($p?->occupation)<option value="" selected>-</option>@endunless
                                    @foreach(['mahasiswa' => 'Mahasiswa', 'pekerja' => 'Pekerja'] as $val => $label)
                                        <option value="{{ $val }}" @selected($p?->occupation === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="sheet-cell form-select" data-url="{{ $url }}" data-field="preferred_location" aria-label="Area {{ $u->name }}">
                                    @unless($p?->preferred_location)<option value="" selected>-</option>@endunless
                                    @foreach(array_unique(array_filter([...$locations, $p?->preferred_location])) as $loc)
                                        <option value="{{ $loc }}" @selected($p?->preferred_location === $loc)>{{ $loc }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input class="sheet-cell form-control" data-url="{{ $url }}" data-field="budget_min" type="number" min="0" step="50000" value="{{ $p?->budget_min }}" placeholder="-" aria-label="Budget min {{ $u->name }}"></td>
                            <td><input class="sheet-cell form-control" data-url="{{ $url }}" data-field="budget_max" type="number" min="0" step="50000" value="{{ $p?->budget_max }}" placeholder="-" aria-label="Budget maks {{ $u->name }}"></td>
                            <td class="sheet-static text-nowrap">
                                <a href="{{ route('admin.users.show', $u->id) }}" class="btn btn-sm btn-link p-0" title="Detail & Rekomendasi"><i class="bi bi-box-arrow-up-right"></i></a>
                            </td>
                        </tr>
                    @empty
                        @include('admin.partials.empty-row', ['colspan' => 9, 'icon' => 'bi-people', 'text' => 'Belum ada data responden.'])
                    @endforelse
                </tbody>
            </table>
            <script>
                (function () {
                    const status = document.getElementById('sheet-status');
                    const say = (text, cls) => { status.className = 'small mb-2 ' + cls; status.textContent = text; };
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
                    document.querySelectorAll('.sheet-cell').forEach(cell => {
                        cell.dataset.saved = cell.value;
                        const save = async () => {
                            if (cell.value === cell.dataset.saved && !cell.classList.contains('is-error')) return;
                            cell.classList.remove('is-saved', 'is-error');
                            cell.classList.add('is-saving');
                            cell.title = '';
                            try {
                                const res = await fetch(cell.dataset.url, {
                                    method: 'PATCH',
                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                                    body: JSON.stringify({ field: cell.dataset.field, value: cell.value }),
                                });
                                const data = await res.json().catch(() => ({}));
                                cell.classList.remove('is-saving');
                                if (!res.ok) {
                                    cell.classList.add('is-error');
                                    cell.title = data.errors?.value?.[0] || data.message || 'Gagal menyimpan.';
                                    say('✕ Belum tersimpan — ' + cell.getAttribute('aria-label') + ': ' + cell.title + ' (Esc untuk batal)', 'text-danger fw-semibold');
                                    return;
                                }
                                cell.dataset.saved = cell.value;
                                cell.querySelector?.('option[value=""]')?.remove();
                                cell.classList.add('is-saved');
                                say('✓ Tersimpan — ' + cell.getAttribute('aria-label'), 'text-success');
                                setTimeout(() => cell.classList.remove('is-saved'), 1500);
                            } catch (e) {
                                cell.classList.remove('is-saving');
                                cell.classList.add('is-error');
                                cell.title = 'Koneksi gagal, coba lagi.';
                                say('✕ Koneksi gagal — ' + cell.getAttribute('aria-label') + ' belum tersimpan.', 'text-danger fw-semibold');
                            }
                        };
                        cell.addEventListener(cell.tagName === 'SELECT' ? 'change' : 'blur', save);
                        cell.addEventListener('keydown', e => {
                            if (e.key === 'Enter') { e.preventDefault(); cell.blur(); if (cell.tagName === 'SELECT') save(); }
                            if (e.key === 'Escape') { cell.value = cell.dataset.saved; cell.classList.remove('is-error'); cell.title = ''; }
                        });
                    });
                    window.addEventListener('beforeunload', e => {
                        if (document.querySelector('.sheet-cell.is-saving, .sheet-cell.is-error')) e.preventDefault();
                    });
                })();
            </script>
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
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.users.edit', $u->id) }}" class="btn btn-sm btn-outline-secondary" title="Ubah data" onclick="event.stopPropagation()">
                                    <i class="bi bi-pencil"></i> Ubah
                                </a>
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
