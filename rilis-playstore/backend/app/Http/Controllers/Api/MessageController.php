<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Illuminate\Http\Request;

/**
 * Pesan langsung penyewa <-> pemilik. Dibatasi ke pasangan role
 * "user" <-> "owner" saja (bukan chat bebas antar sesama pengguna) supaya
 * fiturnya tetap sesuai tujuannya: tanya-jawab seputar kos, bukan medsos.
 */
class MessageController extends Controller
{
    /**
     * Daftar percakapan pengguna login -- satu baris per lawan bicara
     * (partner), diurutkan dari pesan terakhir. Dihitung langsung dari
     * tabel messages (bukan tabel conversations terpisah).
     */
    public function conversations(Request $request)
    {
        $userId = $request->user()->id;

        // Ambil ID setiap lawan bicara yang pernah terlibat pesan dengan
        // user ini, baik sebagai pengirim maupun penerima.
        $partnerIds = Message::where('sender_id', $userId)->pluck('receiver_id')
            ->merge(Message::where('receiver_id', $userId)->pluck('sender_id'))
            ->unique()
            ->values();

        $conversations = $partnerIds->map(function ($partnerId) use ($userId) {
            $partner = User::find($partnerId, ['id', 'name', 'role']);
            if (!$partner) {
                return null;
            }

            // Urut created_at LALU id -- dua pesan bisa tercatat di detik
            // yang sama (mis. dikirim beruntun cepat / dalam satu test),
            // jadi id dipakai sebagai tie-breaker biar urutannya deterministik.
            $lastMessage = Message::where(function ($q) use ($userId, $partnerId) {
                $q->where('sender_id', $userId)->where('receiver_id', $partnerId);
            })->orWhere(function ($q) use ($userId, $partnerId) {
                $q->where('sender_id', $partnerId)->where('receiver_id', $userId);
            })->with('kos:id,name')->orderByDesc('created_at')->orderByDesc('id')->first();

            $unreadCount = Message::where('sender_id', $partnerId)
                ->where('receiver_id', $userId)
                ->whereNull('read_at')
                ->count();

            return [
                'user' => $partner,
                'last_message' => $lastMessage,
                'unread_count' => $unreadCount,
            ];
        })->filter()->sortByDesc(fn ($c) => $c['last_message']->id)->values();

        return response()->json(['conversations' => $conversations]);
    }

    /**
     * Riwayat pesan dengan satu lawan bicara tertentu, urut lama -> baru.
     * Sekalian menandai pesan MASUK (dari lawan bicara ke user ini) sebagai
     * sudah dibaca -- persis seperti pola read-receipt aplikasi chat pada
     * umumnya (dibaca otomatis begitu thread dibuka).
     */
    public function thread(Request $request, $userId)
    {
        $me = $request->user()->id;

        $messages = Message::where(function ($q) use ($me, $userId) {
            $q->where('sender_id', $me)->where('receiver_id', $userId);
        })->orWhere(function ($q) use ($me, $userId) {
            $q->where('sender_id', $userId)->where('receiver_id', $me);
        })->with('kos:id,name')->oldest()->get();

        Message::where('sender_id', $userId)
            ->where('receiver_id', $me)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['messages' => $messages]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'receiver_id' => 'required|integer|exists:users,id',
            // "body" boleh kosong KALAU ada foto (pesan cuma-foto tanpa
            // teks, sama seperti WhatsApp) -- makanya bukan "required" di
            // sini, dicek manual di bawah supaya salah satunya wajib ada.
            'body' => 'nullable|string|max:2000',
            'photo' => 'nullable|mimes:jpeg,jpg,png,webp,gif|max:4096',
            'kos_id' => 'nullable|integer|exists:koses,id',
        ]);

        if (blank($validated['body'] ?? null) && !$request->hasFile('photo')) {
            return response()->json(['message' => 'Pesan tidak boleh kosong -- isi teks atau lampirkan foto.'], 422);
        }

        $sender = $request->user();
        $receiver = User::find($validated['receiver_id']);

        if ($receiver->id === $sender->id) {
            return response()->json(['message' => 'Tidak bisa mengirim pesan ke diri sendiri.'], 422);
        }

        $rolePair = [$sender->role, $receiver->role];
        sort($rolePair);
        if ($rolePair !== ['owner', 'user']) {
            return response()->json(['message' => 'Pesan langsung hanya berlaku antara penyewa dan pemilik kos.'], 422);
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
            // Kalau server Reverb (php artisan reverb:start) sedang tidak
            // jalan, ini gagal dengan exception koneksi -- ditangkap di
            // sini supaya pesan tetap TERSIMPAN & terkirim lewat cara
            // biasa (fetch/polling), cuma tidak instan real-time-nya.
            MessageSent::dispatch($message);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => 'Pesan terkirim.',
            'data' => $message->load('kos:id,name'),
        ], 201);
    }

    /**
     * Total pesan belum dibaca lintas semua percakapan -- dipakai badge
     * notifikasi (nav mobile & web), dihitung ringan (cuma count, bukan
     * ambil semua baris) supaya murah dipanggil sering.
     */
    public function unreadCount(Request $request)
    {
        $count = Message::where('receiver_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['unread_count' => $count]);
    }
}
