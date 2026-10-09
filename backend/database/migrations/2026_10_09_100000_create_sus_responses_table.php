<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jawaban kuesioner System Usability Scale dari responden uji coba online
 * (coba.koskita) -- diisi lewat form publik /kuesioner setelah responden
 * memakai aplikasi versi web. Satu akun hanya boleh mengisi sekali
 * (unique user_id) supaya rata-rata skor tidak tercemar pengisian ganda.
 *
 * Nama & email disimpan sebagai SALINAN supaya jawaban tetap bisa dibaca
 * di Bab IV walau akun respondennya nanti dihapus (user_id jadi null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sus_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('respondent_name');
            $table->string('respondent_email');
            // 10 pernyataan baku SUS (Brooke, 1996), skala Likert 1-5.
            $table->unsignedTinyInteger('q1');
            $table->unsignedTinyInteger('q2');
            $table->unsignedTinyInteger('q3');
            $table->unsignedTinyInteger('q4');
            $table->unsignedTinyInteger('q5');
            $table->unsignedTinyInteger('q6');
            $table->unsignedTinyInteger('q7');
            $table->unsignedTinyInteger('q8');
            $table->unsignedTinyInteger('q9');
            $table->unsignedTinyInteger('q10');
            $table->float('sus_score'); // 0-100, dihitung SusResponse::calculateScore()
            // Pendapat terbuka soal tampilan & fitur -- pelengkap kualitatif
            // untuk angka SUS, opsional.
            $table->text('feedback')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sus_responses');
    }
};
