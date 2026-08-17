@extends('errors.layout')

@section('title', 'Halaman Tidak Ditemukan')
@section('code', '404')
@section('heading', 'Halaman Tidak Ditemukan')
@section('description', 'Halaman yang kamu cari sudah dipindah, dihapus, atau memang tidak pernah ada. Coba cek lagi alamatnya, atau lanjut cari kos dari sini.')

@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">Kembali ke Beranda</a>
    <a href="{{ url('/kos') }}" class="btn btn-outline">Cari Kos</a>
@endsection
