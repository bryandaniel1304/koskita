<?php

namespace App\Http\Controllers\Web;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Illuminate\Http\Request;

/**
 * Versi situs dari pesan langsung penyewa <-> pemilik -- logika bisnis
 * (pembatasan role, tandai dibaca saat thread dibuka) persis sama dengan
 * Api\MessageController, cuma medium beda (Blade + redirect, bukan JSON).
 */
class WebMessageController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $partnerIds = Message::where('sender_id', $userId)->pluck('receiver_id')
            ->merge(Message::where('receiver_id', $userId)->pluck('sender_id'))
            ->unique()
            ->values();

        $conversations = $partnerIds->map(function ($partnerId) use ($userId) {
            $partner = User::find($partnerId, ['id', 'name', 'role']);
            if (!$partner) {
                return null;
            }

            $lastMessage = Message::where(function ($q) use ($userId, $partnerId) {
                $q->where('sender_id', $userId)->where('receiver_id', $partnerId);
            })->orWhere(function ($q) use ($userId, $partnerId) {
                $q->where('sender_id', $partnerId)->where('receiver_id', $userId);
            })->orderByDesc('created_at')->orderByDesc('id')->first();

            $unreadCount = Message::where('sender_id', $partnerId)
                ->where('receiver_id', $userId)
                ->whereNull('read_at')
                ->count();

            return (object) [
                'partner' => $partner,
                'last_message' => $lastMessage,
                'unread_count' => $unreadCount,
            ];
        })->filter()->sortByDesc(fn ($c) => $c->last_message->id)->values();

        return view('web.messages.index', compact('conversations'));
    }

    public function thread(Request $request, $userId)
    {
        $me = $request->user()->id;
        $partner = User::findOrFail($userId);

        $messages = Message::where(function ($q) use ($me, $userId) {
            $q->where('sender_id', $me)->where('receiver_id', $userId);
        })->orWhere(function ($q) use ($me, $userId) {
            $q->where('sender_id', $userId)->where('receiver_id', $me);
        })->with('kos:id,name')->oldest()->get();

        Message::where('sender_id', $userId)
            ->where('receiver_id', $me)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Dibawa dari link "Chat" di halaman detail kos -- disisipkan sebagai
        // field kos_id tersembunyi di form kirim pesan, supaya pesan pertama
        // otomatis punya konteks "Tentang: <kos>" tanpa penyewa perlu jelaskan lagi.
        $kosId = $request->query('kos_id');

        return view('web.messages.thread', compact('messages', 'partner', 'kosId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'receiver_id' => 'required|integer|exists:users,id',
            'body' => 'nullable|string|max:2000',
            'photo' => 'nullable|mimes:jpeg,jpg,png,webp,gif|max:4096',
            'kos_id' => 'nullable|integer|exists:koses,id',
        ]);

        if (blank($validated['body'] ?? null) && !$request->hasFile('photo')) {
            return back()->withErrors(['message' => 'Pesan tidak boleh kosong -- isi teks atau lampirkan foto.']);
        }

        $sender = $request->user();
        $receiver = User::findOrFail($validated['receiver_id']);

        if ($receiver->id === $sender->id) {
            return back()->withErrors(['message' => 'Tidak bisa mengirim pesan ke diri sendiri.']);
        }

        $rolePair = [$sender->role, $receiver->role];
        sort($rolePair);
        if ($rolePair !== ['owner', 'user']) {
            return back()->withErrors(['message' => 'Pesan langsung hanya berlaku antara penyewa dan pemilik kos.']);
        }

        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'kos_id' => $validated['kos_id'] ?? null,
            'body' => $validated['body'] ?? '',
            'photo_path' => $request->hasFile('photo') ? $request->file('photo')->store('message-photos', 'public') : null,
        ]);

        $message->setRelation('sender', $sender);

        try {
            $receiver->notify(new NewMessageReceived($message));
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            // Kalau server Reverb tidak jalan, gagal diam-diam -- pesan
            // tetap tersimpan & tetap kelihatan lewat fetch/refresh biasa.
            MessageSent::dispatch($message);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('web.messages.thread', $receiver->id);
    }
}
