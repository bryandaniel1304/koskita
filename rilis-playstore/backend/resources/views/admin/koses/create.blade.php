@extends('layouts.admin')

@section('title', 'Tambah Kos')
@section('page_name', 'Tambah Kos Baru')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.koses.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Kembali ke Daftar Kos
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert" style="border-radius: 12px;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> Harap perbaiki kesalahan input Anda.
    </div>
@endif

<div class="card-custom">
    <form action="{{ route('admin.koses.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row">
            <!-- Left Side: Basic Info -->
            <div class="col-md-8">
                <div class="mb-3">
                    <label for="name" class="form-label fw-semibold">Nama Kos</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required placeholder="Contoh: Kost Exclusive Karawaci">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="price" class="form-label fw-semibold">Harga Sewa (Per Bulan)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control @error('price') is-invalid @enderror" id="price" name="price" value="{{ old('price') }}" required placeholder="Contoh: 2500000">
                        </div>
                        @error('price')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="gender_type" class="form-label fw-semibold">Tipe Kos</label>
                        <select class="form-select @error('gender_type') is-invalid @enderror" id="gender_type" name="gender_type" required>
                            <option value="" disabled selected>Pilih Tipe...</option>
                            <option value="putra" {{ old('gender_type') === 'putra' ? 'selected' : '' }}>Putra</option>
                            <option value="putri" {{ old('gender_type') === 'putri' ? 'selected' : '' }}>Putri</option>
                            <option value="campur" {{ old('gender_type') === 'campur' ? 'selected' : '' }}>Campur</option>
                        </select>
                        @error('gender_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="location" class="form-label fw-semibold">Area / Wilayah</label>
                        <input type="text" class="form-control @error('location') is-invalid @enderror" id="location" name="location" value="{{ old('location') }}" required placeholder="Contoh: Karawaci atau BSD">
                        @error('location')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="distance_to_campus" class="form-label fw-semibold">Jarak ke Kampus UPH (km)</label>
                        <input type="number" step="0.1" class="form-control @error('distance_to_campus') is-invalid @enderror" id="distance_to_campus" name="distance_to_campus" value="{{ old('distance_to_campus') }}" required placeholder="Contoh: 1.2">
                        @error('distance_to_campus')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mb-3" style="max-width: 260px;">
                    <label for="total_rooms" class="form-label fw-semibold">Jumlah Kamar</label>
                    <input type="number" min="1" class="form-control @error('total_rooms') is-invalid @enderror" id="total_rooms" name="total_rooms" value="{{ old('total_rooms', 1) }}" required placeholder="Contoh: 5">
                    @error('total_rooms')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="latitude" class="form-label fw-semibold">Latitude (opsional, buat pin peta)</label>
                        <input type="number" step="0.0000001" class="form-control @error('latitude') is-invalid @enderror" id="latitude" name="latitude" value="{{ old('latitude') }}" placeholder="-6.2088">
                        @error('latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="longitude" class="form-label fw-semibold">Longitude (opsional)</label>
                        <input type="number" step="0.0000001" class="form-control @error('longitude') is-invalid @enderror" id="longitude" name="longitude" value="{{ old('longitude') }}" placeholder="106.6003">
                        @error('longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="image_url" class="form-label fw-semibold">URL Gambar Kos (fallback)</label>
                    <input type="url" class="form-control @error('image_url') is-invalid @enderror" id="image_url" name="image_url" value="{{ old('image_url') }}" placeholder="https://images.unsplash.com/...">
                    @error('image_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted">Dipakai kalau belum ada foto yang diunggah di bawah.</small>
                </div>

                <div class="mb-3">
                    <label for="photos" class="form-label fw-semibold">Unggah Foto Kos (bisa lebih dari satu)</label>
                    <input type="file" class="form-control @error('photos.*') is-invalid @enderror" id="photos" name="photos[]" accept="image/*" multiple>
                    @error('photos.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted">Foto pertama yang diunggah otomatis jadi foto sampul.</small>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label fw-semibold">Deskripsi Lengkap</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4" placeholder="Fasilitas kamar, aturan menginap, info lingkungan sekitar...">{{ old('description') }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <!-- Right Side: Facilities & Rules checkboxes -->
            <div class="col-md-4">
                <div class="card p-3 mb-3 border-secondary-subtle">
                    <h6 class="fw-bold mb-3 border-bottom pb-2">Fasilitas Kos</h6>
                    @foreach($facilities as $facility)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="facilities[]" value="{{ $facility->id }}" id="fac-{{ $facility->id }}" {{ is_array(old('facilities')) && in_array($facility->id, old('facilities')) ? 'checked' : '' }}>
                            <label class="form-check-label" for="fac-{{ $facility->id }}">
                                {{ $facility->name }}
                            </label>
                        </div>
                    @endforeach
                </div>

                <div class="card p-3 border-secondary-subtle">
                    <h6 class="fw-bold mb-3 border-bottom pb-2">Aturan Kos</h6>
                    @foreach($rules as $rule)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="rules[]" value="{{ $rule->id }}" id="rule-{{ $rule->id }}" {{ is_array(old('rules')) && in_array($rule->id, old('rules')) ? 'checked' : '' }}>
                            <label class="form-check-label" for="rule-{{ $rule->id }}">
                                {{ $rule->name }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-4 pt-3 border-top d-flex gap-2">
            <button type="submit" class="btn btn-primary-custom">Simpan Data Kos</button>
            <a href="{{ route('admin.koses.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>
</div>
@endsection
