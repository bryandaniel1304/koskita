@extends('layouts.admin')

@php($isTenant = $targetUser->role === 'user')
@section('title', $isTenant ? 'Ubah Data Responden' : 'Ubah Data Pengguna')
@section('page_name', ($isTenant ? 'Ubah Data Responden: ' : 'Ubah Data: ') . $targetUser->name)

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.users.show', $targetUser->id) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali ke Detail</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php($profile = $targetUser->profile)
<form action="{{ route('admin.users.update-data', $targetUser->id) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="card-custom mb-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-person"></i> Data Akun</h6>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label small fw-semibold" for="name">Nama</label>
                <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $targetUser->name) }}" required maxlength="255">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold" for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $targetUser->email) }}" required maxlength="255">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold" for="phone">Nomor HP</label>
                <input type="text" id="phone" name="phone" class="form-control" value="{{ old('phone', $targetUser->phone) }}" maxlength="20">
            </div>
        </div>
        <p class="small text-muted mt-2 mb-0">Role dan status Master Admin diubah dari halaman detail.</p>
    </div>

    @if($isTenant)
        <div class="card-custom mb-4">
            <h6 class="fw-bold mb-1"><i class="bi bi-person-badge"></i> Profil Preferensi</h6>
            <p class="small text-muted mb-3">
                Sama dengan isian profil di aplikasi. Mengubahnya mengubah vektor preferensi, jadi rekomendasi pengguna ini ikut berubah.
                @if(!$profile?->completed_at)
                    <br><strong>Profil ini belum pernah dilengkapi pengguna;</strong> setelah disimpan, profil dianggap lengkap dan rekomendasinya dihitung.
                @endif
            </p>
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold" for="gender">Gender</label>
                    <select id="gender" name="gender" class="form-select" required>
                        @foreach(['pria' => 'Pria', 'wanita' => 'Wanita'] as $val => $label)
                            <option value="{{ $val }}" {{ old('gender', $profile?->gender) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold" for="occupation">Pekerjaan</label>
                    <select id="occupation" name="occupation" class="form-select" required>
                        @foreach(['mahasiswa' => 'Mahasiswa', 'pekerja' => 'Pekerja'] as $val => $label)
                            <option value="{{ $val }}" {{ old('occupation', $profile?->occupation) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="preferred_location">Area Preferensi</label>
                    <select id="preferred_location" name="preferred_location" class="form-select" required>
                        @foreach($locations as $loc)
                            <option value="{{ $loc }}" {{ old('preferred_location', $profile?->preferred_location) === $loc ? 'selected' : '' }}>{{ $loc }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="budget_min">Budget Minimum (Rp/bulan)</label>
                    <input type="number" id="budget_min" name="budget_min" class="form-control" min="0" step="50000" value="{{ old('budget_min', $profile?->budget_min) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="budget_max">Budget Maksimum (Rp/bulan)</label>
                    <input type="number" id="budget_max" name="budget_max" class="form-control" min="0" step="50000" value="{{ old('budget_max', $profile?->budget_max) }}" required>
                </div>
            </div>

            @php($selectedFacilities = old('preferred_facilities', $profile?->preferred_facilities ?? []))
            <label class="form-label small fw-semibold">Fasilitas yang Diinginkan</label>
            <div class="border rounded-3 p-3 mb-3" style="max-height: 240px; overflow-y: auto;">
                <div class="row g-1">
                    @foreach($facilityOptions as $i => $facility)
                        <div class="col-sm-6 col-lg-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="preferred_facilities[]" id="fac-{{ $i }}" value="{{ $facility }}" {{ in_array($facility, $selectedFacilities, true) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="fac-{{ $i }}">{{ $facility }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @php($selectedRules = old('preferred_rules', $profile?->preferred_rules ?? []))
            <label class="form-label small fw-semibold">Aturan yang Diinginkan</label>
            <div class="border rounded-3 p-3">
                <div class="row g-1">
                    @foreach($ruleOptions as $i => $rule)
                        <div class="col-sm-6 col-lg-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="preferred_rules[]" id="rule-{{ $i }}" value="{{ $rule }}" {{ in_array($rule, $selectedRules, true) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="rule-{{ $i }}">{{ $rule }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('admin.users.show', $targetUser->id) }}" class="btn btn-outline-secondary">Batal</a>
        <button type="submit" class="btn btn-primary-custom"><i class="bi bi-save"></i> Simpan Perubahan</button>
    </div>
</form>
@endsection
