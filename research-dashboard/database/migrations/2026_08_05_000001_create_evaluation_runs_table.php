<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('strategy'); // hybrid | cb_only | cf_only | popularity
            $table->float('alpha')->nullable();
            $table->unsignedInteger('k'); // nilai K (Top-K) evaluasi ini
            $table->unsignedInteger('users_evaluated');
            $table->float('precision');
            $table->float('recall');
            $table->float('ndcg');
            $table->float('map');
            $table->string('batch_label')->nullable(); // pengelompokan (mis. "perbandingan-alpha-2026-08-05")
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_runs');
    }
};
