<?php

namespace App\Support;

/**
 * Parameter koneksi WebSocket yang boleh diketahui client (web & Flutter),
 * untuk driver broadcasting yang sedang aktif.
 *
 * Dua driver didukung karena lingkungannya berbeda:
 * - reverb: server WebSocket milik sendiri (`php artisan reverb:start`),
 *   dipakai saat pengembangan lokal / di VPS.
 * - pusher: layanan Pusher Channels, dipakai di shared hosting (cPanel)
 *   yang tidak mengizinkan proses WebSocket berjalan terus-menerus.
 *   Reverb bicara protokol Pusher, jadi client cukup ganti alamat server.
 *
 * Secret TIDAK pernah ikut -- hanya key publik, sama seperti key publik
 * Pusher/Firebase yang memang dirancang untuk dibaca client.
 */
class RealtimeConfig
{
    /**
     * @return array{driver: string, key: ?string, host: ?string, port: int, scheme: string, cluster: ?string}
     */
    public static function forClient(): array
    {
        if (config('broadcasting.default') === 'pusher') {
            $cluster = config('broadcasting.connections.pusher.options.cluster') ?: 'mt1';

            return [
                'driver' => 'pusher',
                'key' => config('broadcasting.connections.pusher.key'),
                // Host WebSocket Pusher per cluster (bukan host REST API
                // `api-<cluster>.pusher.com` yang dipakai backend untuk
                // memicu event).
                'host' => "ws-{$cluster}.pusher.com",
                'port' => 443,
                'scheme' => 'wss',
                'cluster' => $cluster,
            ];
        }

        return [
            'driver' => 'reverb',
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => config('broadcasting.connections.reverb.options.host'),
            'port' => (int) config('broadcasting.connections.reverb.options.port'),
            'scheme' => config('broadcasting.connections.reverb.options.useTLS') ? 'wss' : 'ws',
            'cluster' => null,
        ];
    }
}
