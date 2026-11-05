<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jawaban kuesioner SUS dari panel Populix. Responden Populix tidak punya
 * akun KosKita, jadi datanya disimpan di database dashboard ini (bukan di
 * tabel sus_responses server KosKita yang mewajibkan user_id). Diisi lewat
 * `php artisan sus:import-populix`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('populix_sus_responses', function (Blueprint $table) {
            $table->id();
            $table->string('populix_id')->unique();
            $table->string('gender')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->dateTime('completed_at')->nullable();
            foreach (range(1, 10) as $n) {
                $table->unsignedTinyInteger("q$n");
            }
            $table->float('sus_score');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('populix_sus_responses');
    }
};
