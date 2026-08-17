<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Badge "Pemilik Terverifikasi" (dokumen identitas ditinjau admin) +
     * kode QRIS statis milik pemilik untuk ditampilkan ke penyewa saat
     * booking dikonfirmasi. Semua nullable/default aman -- additive murni.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('owner_verification_status')->default('none')->after('role'); // none|pending|approved|rejected
            $table->string('owner_verification_document')->nullable()->after('owner_verification_status');
            $table->timestamp('owner_verified_at')->nullable()->after('owner_verification_document');
            $table->string('qris_image_path')->nullable()->after('owner_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['owner_verification_status', 'owner_verification_document', 'owner_verified_at', 'qris_image_path']);
        });
    }
};
