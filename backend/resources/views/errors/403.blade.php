@extends('errors.layout')

@section('title', 'Akses Ditolak')
@section('code', '403')
@section('heading', 'Kamu Tidak Punya Akses ke Sini')
@section('description', 'Halaman ini mungkin khusus untuk peran lain (mis. Portal Pemilik), atau butuh login dengan akun yang berbeda.')

@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">Kembali ke Beranda</a>
@endsection
