<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Booking extends Model
{
    /** Karakter kode booking -- tanpa I/O/0/1 yang gampang salah didikte
     *  penyewa saat konfirmasi manual (lihat migration add_code_to_bookings). */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Kolom yang perubahannya dicatat ke riwayat booking_activity_logs. */
    private const AUDITED_FIELDS = ['status', 'payment_status'];

    /** `code` sengaja TIDAK ikut fillable -- nomor pengajuan dibuat sistem
     *  di creating(), jangan sampai bisa dikirim dari request. */
    protected $fillable = [
        'user_id',
        'kos_id',
        'start_date',
        'duration_months',
        'notes',
        'status',
        'admin_note',
        'payment_status',
        'paid_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'paid_at' => 'datetime',
    ];

    /** Samakan dengan default kolom di migration -- tanpa ini, model yang
     *  baru dibuat tidak tahu nilai awalnya (default diisi DB, bukan PHP),
     *  sehingga perubahan pertamanya tercatat "dari kosong" di riwayat. */
    protected $attributes = [
        'status' => 'pending',
        'payment_status' => 'unpaid',
    ];

    /**
     * Nomor pengajuan & riwayat perubahan dipasang di level MODEL, bukan
     * di masing-masing controller -- perubahan status booking datang dari
     * empat jalur berbeda (panel admin, dashboard pemilik web & API, dan
     * pembatalan oleh penyewa), jadi kalau pencatatannya ditaruh di
     * controller pasti ada jalur yang kelewat dan jejak auditnya bolong.
     */
    protected static function booted(): void
    {
        static::creating(function (self $booking) {
            $booking->code ??= static::generateUniqueCode();
        });

        static::updated(function (self $booking) {
            foreach (self::AUDITED_FIELDS as $field) {
                if ($booking->wasChanged($field)) {
                    $booking->recordActivity($field, $booking->getOriginal($field), $booking->$field);
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kos()
    {
        return $this->belongsTo(Kos::class);
    }

    /** Riwayat perubahan status -- ditampilkan di detail booking panel admin. */
    public function activityLogs()
    {
        return $this->hasMany(BookingActivityLog::class)->latest();
    }

    /** Nomor pengajuan yang unik, format KK-YYMMDD-XXXX. Diulang kalau
     *  kebetulan bentrok -- kolomnya juga unique di DB sebagai jaring
     *  pengaman terakhir kalau dua request lolos barengan. */
    public static function generateUniqueCode(): string
    {
        do {
            $random = '';
            for ($i = 0; $i < 4; $i++) {
                $random .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $code = 'KK-' . now()->format('ymd') . '-' . $random;
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Tulis satu baris riwayat. Pelaku diambil dari sesi/token yang sedang
     * aktif -- null (mis. dijalankan dari seeder, command, atau scheduler)
     * dicatat sebagai "Sistem", bukan disembunyikan, supaya tidak ada
     * perubahan status yang hilang dari riwayat.
     */
    protected function recordActivity(string $field, ?string $from, string $to): void
    {
        $actor = Auth::user();

        $this->activityLogs()->create([
            'field' => $field,
            'from_value' => $from,
            'to_value' => $to,
            'changed_by' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_role' => $actor
                ? ($actor->isMasterAdmin() ? 'master_admin' : $actor->role)
                : 'sistem',
            // Catatan admin yang diisi BARENGAN dengan perubahan status
            // ikut menempel di baris riwayatnya -- itu alasan perubahannya.
            'note' => $field === 'status' && $this->wasChanged('admin_note') ? $this->admin_note : null,
        ]);
    }

    /**
     * Dipakai buat memutuskan siapa yang BOLEH menulis ulasan baru --
     * sengaja "completed" saja (bukan "confirmed" juga), karena "confirmed"
     * cuma berarti pemilik menyetujui, belum tentu masa sewanya sudah
     * selesai/sungguh-sungguh sudah menginap. Dipakai bersama oleh
     * Api\ReviewController, Web\WebKosController, dan tempat lain yang
     * perlu tahu status ini (mis. tampilkan/sembunyikan tombol ulasan)
     * supaya syaratnya konsisten di satu tempat saja.
     */
    public static function userHasCompletedStayAt(int $userId, int $kosId): bool
    {
        return static::where('user_id', $userId)
            ->where('kos_id', $kosId)
            ->where('status', 'completed')
            ->exists();
    }
}
