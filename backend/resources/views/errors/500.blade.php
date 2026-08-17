@extends('errors.layout')

@section('title', 'Terjadi Kesalahan Server')
@section('code', '500')
@section('heading', 'Ada yang Salah di Server Kami')
@section('description', 'Bukan salah kamu -- ada masalah teknis di sisi kami. Tim KosKita sudah otomatis diberi tahu. Coba lagi sebentar lagi ya.')

@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">Kembali ke Beranda</a>
@endsection
