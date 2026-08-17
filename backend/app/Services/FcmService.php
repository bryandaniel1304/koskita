<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push notification asli lewat Firebase Cloud Messaging (HTTP v1 API) --
 * TANPA package tambahan (mis. kreait/laravel-firebase), cukup openssl
 * bawaan PHP buat tanda tangan JWT server-to-server. Alasan pakai HTTP v1
 * (bukan "Legacy HTTP API" yang server key-nya lebih sederhana): Google
 * sudah mematikan total Legacy API -- HTTP v1 satu-satunya yang masih
 * berfungsi, dan itu WAJIB otentikasi lewat OAuth2 service account.
 *
 * KOSONG SENGAJA sampai admin mengisi FIREBASE_PROJECT_ID/CLIENT_EMAIL/
 * PRIVATE_KEY di .env (lihat catatan setup lengkap di .env.example) --
 * configured() dicek di awal, kalau false semua operasi jadi no-op AMAN
 * (bukan error), notifikasi tetap terkirim lewat email seperti biasa.
 */
class FcmService
{
    public static function configured(): bool
    {
        return filled(config('services.fcm.project_id'))
            && filled(config('services.fcm.client_email'))
            && filled(config('services.fcm.private_key'));
    }

    /**
     * Kirim push ke SEMUA perangkat terdaftar milik satu user. Token yang
     * ternyata sudah tidak valid lagi (uninstall/logout paksa dari sisi
     * Google) otomatis dihapus dari fcm_tokens supaya tidak terus dicoba
     * kirim ke token mati di masa depan.
     */
    public static function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if (!self::configured()) {
            return;
        }

        $tokens = $user->fcmTokens()->pluck('token', 'id');
        if ($tokens->isEmpty()) {
            return;
        }

        $accessToken = self::getAccessToken();
        if ($accessToken === null) {
            return;
        }

        foreach ($tokens as $tokenId => $token) {
            self::sendToToken($accessToken, $token, $title, $body, $data, $tokenId);
        }
    }

    private static function sendToToken(string $accessToken, string $token, string $title, string $body, array $data, int $tokenId): void
    {
        $projectId = config('services.fcm.project_id');

        try {
            $response = Http::withToken($accessToken)
                ->timeout(5)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        // Semua value data payload WAJIB string per spesifikasi FCM.
                        'data' => array_map('strval', $data),
                    ],
                ]);

            if (!$response->successful()) {
                $status = $response->json('error.status');
                // Token sudah tidak valid lagi (uninstall/logout paksa Google)
                // -- hapus supaya tidak terus dicoba di masa depan.
                if (in_array($status, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true)) {
                    FcmToken::whereKey($tokenId)->delete();
                }
                Log::warning('FCM send failed', ['status' => $status, 'body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Access token OAuth2 di-cache ~55 menit (masa berlaku aslinya 1 jam)
     * supaya tidak mint token baru di SETIAP push -- signing JWT + round-
     * trip ke Google cukup costly kalau dilakukan berulang untuk
     * notifikasi yang datang beruntun (mis. banyak pesan chat berturut).
     */
    private static function getAccessToken(): ?string
    {
        return Cache::remember('fcm_access_token', 3300, function () {
            try {
                $jwt = self::buildSignedJwt();
                $response = Http::asForm()->timeout(5)->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                if (!$response->successful()) {
                    Log::warning('FCM OAuth2 token exchange failed', ['body' => $response->body()]);
                    return null;
                }

                return $response->json('access_token');
            } catch (\Throwable $e) {
                report($e);
                return null;
            }
        });
    }

    /**
     * JWT assertion (RS256) yang membuktikan ke Google kita memang pemilik
     * service account ini -- ditandatangani pakai private key-nya sendiri,
     * kredensialnya sendiri TIDAK PERNAH dikirim lewat jaringan dalam
     * bentuk apa pun (beda dari alur password/API-key biasa).
     */
    private static function buildSignedJwt(): string
    {
        $clientEmail = config('services.fcm.client_email');
        // Private key PEM disimpan dengan literal "\n" di .env (file .env
        // tidak bisa memuat baris baru sungguhan) -- diterjemahkan balik
        // jadi baris baru asli di sini sebelum dipakai openssl_sign().
        $privateKey = str_replace('\\n', "\n", config('services.fcm.private_key'));

        $now = time();
        $header = self::base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = self::base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signingInput = "{$header}.{$claims}";
        openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $signingInput . '.' . self::base64UrlEncode($signature);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
