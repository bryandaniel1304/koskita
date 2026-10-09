@extends('web.sus.layout')

@section('title', 'Kuesioner Pengalaman Pengguna')

@section('content')
<style>
    .likert { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
    .likert label { display: flex; align-items: center; justify-content: center; height: 46px; border: 1.5px solid #E2E8F0; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all .15s; user-select: none; }
    .likert input:checked + label { background: var(--brand); border-color: var(--brand); color: #fff; }
    .likert label:hover { border-color: var(--brand); }
    .likert-caption { display: flex; justify-content: space-between; font-size: .75rem; color: #64748B; margin-top: 6px; }
    .question { padding: 1.25rem 0; border-bottom: 1px solid #F1F5F9; }
    .question:last-of-type { border-bottom: 0; }
    .question.is-missing .question-text { color: #DC2626; }
</style>

<div class="sus-card p-4 p-md-5">
    <h1 class="h4 fw-bold mb-2">Kuesioner Pengalaman Pengguna</h1>
    <p class="text-muted mb-4">
        Terima kasih sudah mencoba aplikasi KosKita! Isi 10 pernyataan berikut sesuai pengalamanmu barusan.
        Tidak ada jawaban benar atau salah, jawab sejujurnya. Pengisian sekitar 3 menit.
    </p>

    @if(collect(array_keys(\App\Models\SusResponse::QUESTIONS))->contains(fn ($n) => $errors->has("q$n")))
        <div class="alert alert-danger rounded-3 small">Mohon jawab semua 10 pernyataan (yang bertanda merah belum dijawab).</div>
    @endif

    <form method="POST" action="{{ route('sus.store') }}" novalidate>
        @csrf

        <div class="mb-4">
            <label for="email" class="form-label fw-semibold">Email akun KosKita kamu</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                   class="form-control @error('email') is-invalid @enderror" placeholder="email yang dipakai saat daftar di aplikasi">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @else
                <div class="form-text">Dipakai untuk mencocokkan jawaban dengan akun uji coba kamu, tidak ditampilkan ke publik.</div>
            @enderror
        </div>

        <p class="small text-muted mb-0"><strong>1</strong> = Sangat Tidak Setuju &nbsp;·&nbsp; <strong>5</strong> = Sangat Setuju</p>

        @foreach(\App\Models\SusResponse::QUESTIONS as $num => $text)
            <div class="question {{ $errors->has("q$num") ? 'is-missing' : '' }}">
                <p class="question-text fw-semibold mb-3">{{ $num }}. {{ $text }}</p>
                <div class="likert" role="radiogroup" aria-label="Pernyataan {{ $num }}">
                    @for($v = 1; $v <= 5; $v++)
                        <input type="radio" class="btn-check" name="q{{ $num }}" id="q{{ $num }}_{{ $v }}" value="{{ $v }}"
                               {{ (string) old("q$num") === (string) $v ? 'checked' : '' }} required>
                        <label for="q{{ $num }}_{{ $v }}">{{ $v }}</label>
                    @endfor
                </div>
                <div class="likert-caption"><span>Sangat tidak setuju</span><span>Sangat setuju</span></div>
            </div>
        @endforeach

        <div class="mt-4 mb-4">
            <label for="feedback" class="form-label fw-semibold">Kesan & saran (opsional)</label>
            <textarea id="feedback" name="feedback" rows="4" maxlength="2000" class="form-control @error('feedback') is-invalid @enderror"
                      placeholder="Misalnya: bagian tampilan yang kamu suka/kurang suka, fitur yang paling membantu, atau fitur yang membingungkan.">{{ old('feedback') }}</textarea>
            @error('feedback')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-brand w-100 py-3">Kirim Jawaban</button>
    </form>
</div>
@endsection
