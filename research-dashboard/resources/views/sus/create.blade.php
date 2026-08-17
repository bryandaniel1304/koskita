@extends('layouts.app')

@section('title', 'Isi Kuesioner SUS')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-custom p-4 p-md-5">
            <h4 class="fw-bold mb-1">Kuesioner Usabilitas KOSKITA</h4>
            <p class="text-muted mb-4">Terima kasih sudah mencoba aplikasi KOSKITA! Isi 10 pernyataan di bawah berdasarkan pengalamanmu memakai aplikasinya. Skala: <strong>1 = Sangat Tidak Setuju</strong>, <strong>5 = Sangat Setuju</strong>.</p>

            @if($errors->any())
                <div class="alert alert-danger">Mohon isi semua pernyataan (1-5).</div>
            @endif

            <form method="POST" action="{{ route('sus.store') }}">
                @csrf
                <div class="mb-4">
                    <label class="form-label fw-semibold">Nama (opsional)</label>
                    <input type="text" name="respondent_name" class="form-control" placeholder="Boleh dikosongkan kalau mau anonim">
                </div>

                @foreach(\App\Models\SusResponse::QUESTIONS as $num => $text)
                    <div class="mb-4 pb-3 border-bottom">
                        <label class="form-label fw-semibold">{{ $num }}. {{ $text }}</label>
                        <div class="d-flex gap-3 mt-2">
                            @for($v = 1; $v <= 5; $v++)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="q{{ $num }}" id="q{{ $num }}_{{ $v }}" value="{{ $v }}" required>
                                    <label class="form-check-label small" for="q{{ $num }}_{{ $v }}">{{ $v }}</label>
                                </div>
                            @endfor
                        </div>
                    </div>
                @endforeach

                <button type="submit" class="btn btn-primary-custom w-100 py-2">Kirim Jawaban</button>
            </form>
        </div>
    </div>
</div>
@endsection
