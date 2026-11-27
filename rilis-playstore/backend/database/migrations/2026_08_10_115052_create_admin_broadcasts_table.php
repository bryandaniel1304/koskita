<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengumuman/broadcast dari admin ke penyewa dan/atau pemilik kos --
 * ditampilkan menyatu di feed notifikasi in-app (lihat NotificationController).
 * Tabel BARU, tidak menyentuh/mengubah tabel manapun yang sudah ada, jadi
 * aman terhadap data eksisting (kos, interaksi, booking, dst. buat sidang).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            // null = ke semua role (penyewa & pemilik)
            $table->enum('target_role', ['user', 'owner'])->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_broadcasts');
    }
};
