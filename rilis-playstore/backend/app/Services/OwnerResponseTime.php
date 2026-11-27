<?php

namespace App\Services;

use App\Models\Message;

/**
 * Badge "Respons Cepat" -- dihitung dari data pesan yang sudah ada
 * (App\Models\Message), tanpa tabel/state tambahan. Untuk tiap lawan bicara
 * (biasanya penyewa) yang PERTAMA KALI mengirim pesan ke pemilik ini, cari
 * balasan PERTAMA pemilik sesudahnya, lalu rata-ratakan selisih waktunya.
 * Cukup murah untuk skala skripsi (jumlah percakapan per pemilik kecil);
 * dipakai batch (bukan per-baris query N+1) lewat averagesFor().
 */
class OwnerResponseTime
{
    /**
     * @param  array<int>  $ownerIds
     * @return array<int, int|null> ownerId => rata-rata menit respons, null kalau belum pernah dibalas
     */
    public static function averagesFor(array $ownerIds): array
    {
        $result = [];

        foreach (array_unique($ownerIds) as $ownerId) {
            $tenantIds = Message::where('receiver_id', $ownerId)->pluck('sender_id')->unique();
            $diffs = [];

            foreach ($tenantIds as $tenantId) {
                $firstIncoming = Message::where('sender_id', $tenantId)
                    ->where('receiver_id', $ownerId)
                    ->oldest()
                    ->first();
                if (!$firstIncoming) {
                    continue;
                }

                // '>=' (bukan '>') SENGAJA -- dua pesan yang dikirim beruntun
                // cepat (mis. dalam detik yang sama) bisa punya created_at
                // identik tergantung presisi kolom timestamp DB. Karena query
                // ini sudah menyaring sender_id = pemilik secara terpisah dari
                // pesan masuk penyewa, tidak ada risiko salah cocok dengan
                // baris yang sama.
                $firstReply = Message::where('sender_id', $ownerId)
                    ->where('receiver_id', $tenantId)
                    ->where('created_at', '>=', $firstIncoming->created_at)
                    ->oldest()
                    ->first();

                if ($firstReply) {
                    $diffs[] = $firstIncoming->created_at->diffInMinutes($firstReply->created_at);
                }
            }

            $result[$ownerId] = empty($diffs) ? null : (int) round(array_sum($diffs) / count($diffs));
        }

        return $result;
    }

    /** Label badge -- sengaja tidak ada label untuk yang lambat (bukan alat mempermalukan pemilik, cuma menonjolkan yang cepat). */
    public static function badgeLabel(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }
        if ($minutes <= 60) {
            return 'Biasanya balas dalam < 1 jam';
        }
        if ($minutes <= 360) {
            return 'Biasanya balas dalam beberapa jam';
        }
        return null;
    }
}
