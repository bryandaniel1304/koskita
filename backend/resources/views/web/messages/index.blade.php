@extends('web.layouts.app')

@section('title', 'Pesan')

@section('content')
<div class="container py-4" style="max-width: 720px;">
    <h4 class="fw-bold mb-4">Pesan</h4>

    @if($conversations->isEmpty())
        <div class="card-koskita p-5 text-center">
            <i class="bi bi-chat-square-text fs-1 text-muted mb-3"></i>
            <h6 class="fw-bold">Belum ada percakapan</h6>
            <p class="text-muted small mb-0">Mulai chat dari halaman detail kos{{ Auth::user()->role === 'owner' ? ' atau daftar booking masuk' : '' }}.</p>
        </div>
    @else
        <div class="card-koskita">
            @foreach($conversations as $c)
                <a href="{{ route('web.messages.thread', $c->partner->id) }}" class="d-flex align-items-center gap-3 p-3 text-decoration-none text-dark {{ !$loop->last ? 'border-bottom' : '' }} {{ $c->unread_count > 0 ? 'bg-primary bg-opacity-10' : '' }}">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 fw-bold text-white" style="width:44px;height:44px;background:var(--primary);">
                        {{ strtoupper(substr($c->partner->name, 0, 1)) }}
                    </div>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-{{ $c->unread_count > 0 ? 'bold' : 'semibold' }}">{{ $c->partner->name }}</span>
                            @if($c->last_message)
                                <small class="text-muted flex-shrink-0 ms-2">{{ $c->last_message->created_at->diffForHumans(null, null, true) }}</small>
                            @endif
                        </div>
                        <p class="small mb-0 text-truncate {{ $c->unread_count > 0 ? 'fw-semibold text-dark' : 'text-muted' }}">{{ $c->last_message->body ?? '' }}</p>
                    </div>
                    @if($c->unread_count > 0)
                        <span class="badge rounded-pill" style="background: var(--primary);">{{ $c->unread_count }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
