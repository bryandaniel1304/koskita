<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sus_responses', function (Blueprint $table) {
            $table->id();
            // Tidak pakai foreign key -- id ini merujuk ke tabel users di
            // database "koskita" (koneksi "source"), lintas database jadi
            // tidak bisa (dan tidak perlu) di-constrain di sini.
            $table->unsignedBigInteger('source_user_id')->nullable();
            $table->string('respondent_name')->nullable(); // jaga-jaga kalau responden tidak match akun (mis. isi manual)
            // 10 pertanyaan baku System Usability Scale (Brooke, 1996), tiap
            // jawaban skala Likert 1-5 (Sangat Tidak Setuju - Sangat Setuju).
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
            $table->float('sus_score'); // skor akhir 0-100 hasil perhitungan baku SUS
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sus_responses');
    }
};
