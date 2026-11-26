<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jawaban SUS kini bisa masuk dari dua jalur: form /kuesioner di KosKita
 * (source = "form") atau unggahan file hasil ekspor Google Form oleh master
 * admin (source = "import"). Jawaban impor tidak wajib punya email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sus_responses', function (Blueprint $table) {
            $table->string('respondent_email')->nullable()->change();
            $table->string('source', 20)->default('form')->after('feedback');
        });
    }

    public function down(): void
    {
        Schema::table('sus_responses', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
