<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris riwayat perubahan status booking -- lihat migration
 * create_booking_activity_logs_table untuk alasan kolom salinan
 * actor_name/actor_role. Ditulis otomatis lewat Booking::booted().
 */
class BookingActivityLog extends Model
{
    protected $fillable = [
        'booking_id',
        'field',
        'from_value',
        'to_value',
        'changed_by',
        'actor_name',
        'actor_role',
        'note',
    ];

    /** Label status pengajuan & pembayaran dalam bahasa Indonesia --
     *  dipakai view riwayat supaya tidak menampilkan nilai mentah DB. */
    public const VALUE_LABELS = [
        'pending' => 'Menunggu',
        'confirmed' => 'Dikonfirmasi',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
        'completed' => 'Selesai',
        'unpaid' => 'Belum Dibayar',
        'paid' => 'Sudah Dibayar',
    ];

    public const FIELD_LABELS = [
        'status' => 'Status Pengajuan',
        'payment_status' => 'Status Pembayaran',
    ];

    public const ACTOR_ROLE_LABELS = [
        'master_admin' => 'Master Admin',
        'admin' => 'Admin',
        'owner' => 'Pemilik Kos',
        'user' => 'Penyewa',
        'sistem' => 'Sistem',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function fieldLabel(): string
    {
        return self::FIELD_LABELS[$this->field] ?? $this->field;
    }

    public function fromLabel(): ?string
    {
        return $this->from_value ? (self::VALUE_LABELS[$this->from_value] ?? $this->from_value) : null;
    }

    public function toLabel(): string
    {
        return self::VALUE_LABELS[$this->to_value] ?? $this->to_value;
    }

    public function actorRoleLabel(): string
    {
        return self::ACTOR_ROLE_LABELS[$this->actor_role] ?? 'Sistem';
    }

    /** Nama pelaku -- pakai nama akun terkini kalau akunnya masih ada,
     *  jatuh ke salinan nama saat kejadian kalau sudah dihapus. */
    public function actorLabel(): string
    {
        return $this->changedBy?->name ?? $this->actor_name ?? 'Sistem';
    }
}
