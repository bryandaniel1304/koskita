@extends('layouts.admin')

@section('title', 'Ubah Jawaban SUS')
@section('page_name', 'Ubah Jawaban SUS: ' . $response->respondent_name)

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.sus.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="alert alert-warning small">
    Jawaban responden adalah data penelitian. Skor SUS dihitung ulang otomatis setelah disimpan.
</div>

<form action="{{ route('admin.sus.update', $response->id) }}" method="POST" class="card-custom">
    @csrf
    @method('PUT')
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <label class="form-label small fw-semibold" for="respondent_name">Nama Responden</label>
            <input type="text" id="respondent_name" name="respondent_name" class="form-control" value="{{ old('respondent_name', $response->respondent_name) }}" required maxlength="255">
        </div>
        <div class="col-md-6">
            <label class="form-label small fw-semibold" for="respondent_email">Email</label>
            <input type="email" id="respondent_email" name="respondent_email" class="form-control" value="{{ old('respondent_email', $response->respondent_email) }}" maxlength="255">
        </div>
    </div>

    <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Pernyataan</th>
                    @foreach([1 => 'STS', 2 => 'TS', 3 => 'N', 4 => 'S', 5 => 'SS'] as $val => $label)
                        <th class="text-center small">{{ $val }}<br><span class="text-muted fw-normal">{{ $label }}</span></th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach(\App\Models\SusResponse::QUESTIONS as $num => $text)
                    <tr>
                        <td class="small text-muted">Q{{ $num }}</td>
                        <td class="small">{{ $text }}</td>
                        @foreach(range(1, 5) as $val)
                            <td class="text-center">
                                <input type="radio" class="form-check-input" name="q{{ $num }}" value="{{ $val }}" aria-label="Q{{ $num }} = {{ $val }}"
                                       {{ (int) old("q$num", $response->{"q$num"}) === $val ? 'checked' : '' }} required>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mb-4">
        <label class="form-label small fw-semibold" for="feedback">Kesan & Saran</label>
        <textarea id="feedback" name="feedback" rows="3" class="form-control" maxlength="2000">{{ old('feedback', $response->feedback) }}</textarea>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <span class="small text-muted">Skor saat ini: <strong>{{ number_format($response->sus_score, 1, ',', '.') }}</strong></span>
        <button type="submit" class="btn btn-primary-custom"><i class="bi bi-save"></i> Simpan Perubahan</button>
    </div>
</form>
@endsection
