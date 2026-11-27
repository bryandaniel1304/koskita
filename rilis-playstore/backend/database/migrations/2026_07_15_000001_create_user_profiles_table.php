<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gender')->nullable(); // pria, wanita
            $table->string('occupation')->nullable(); // mahasiswa, pekerja
            $table->integer('budget_min')->nullable();
            $table->integer('budget_max')->nullable();
            $table->json('preferred_facilities')->nullable(); // array of facility names/IDs
            $table->json('preferred_rules')->nullable(); // array of rule names/IDs
            $table->string('preferred_location')->nullable(); // e.g. Tangerang, Karawaci, BSD
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
