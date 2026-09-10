<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor unik pengajuan sewa -- ID auto-increment tidak dipakai sebagai
 * nomor resmi karena bocorin jumlah booking di sistem & susah dibacakan
 * lewat telepon/WhatsApp saat penyewa menanyakan pengajuannya. Format
 * KK-YYMMDD-XXXX (mis. KK-260910-J7Q2): ada tanggal pengajuan supaya
 * admin langsung tahu umurnya, ditambah 4 karakter acak sebagai pembeda.
 *
 * Alfabet acaknya sengaja tanpa I/O/0/1 -- karakter yang paling sering
 * salah dibaca/didikte penyewa saat konfirmasi manual.
 *
 * Kolom dibikin nullable di level DB (bukan NOT NULL) supaya baris lama
 * tidak menghalangi migrasi; pengisiannya dijamin di Booking::creating,
 * dan baris lama di-backfill di bawah ini.
 */
return new class extends Migration
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');
        });

        // Backfill booking lama -- pakai tanggal pengajuan aslinya supaya
        // nomornya tetap konsisten dengan arti formatnya.
        DB::table('bookings')->whereNull('code')->orderBy('id')->each(function ($booking) {
            DB::table('bookings')->where('id', $booking->id)->update([
                'code' => $this->generateUniqueCode($booking->created_at),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }

    private function generateUniqueCode(?string $createdAt): string
    {
        $date = $createdAt ? date('ymd', strtotime($createdAt)) : now()->format('ymd');

        do {
            $random = '';
            for ($i = 0; $i < 4; $i++) {
                $random .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $code = "KK-{$date}-{$random}";
        } while (DB::table('bookings')->where('code', $code)->exists());

        return $code;
    }
};
