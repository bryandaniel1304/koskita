<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris per PERANGKAT (bukan per pengguna) -- pengguna yang
     * login di 2 HP akan punya 2 baris, dan push dikirim ke semuanya.
     * Token bersifat UNIK secara global (bukan unik per user_id) karena
     * kalau ada 2 akun yang login bergantian di HP yang sama, token FCM
     * fisiknya sama persis -- kepemilikan dipindah ke user_id yang lagi
     * login lewat updateOrCreate(['token' => ...], ...), lihat
     * Api\FcmTokenController::store().
     */
    public function up(): void
    {
        Schema::create('fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('device_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fcm_tokens');
    }
};
