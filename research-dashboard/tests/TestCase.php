<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tes tidak boleh menyentuh MySQL "koskita" sungguhan: koneksi
        // "source" diarahkan ke SQLite di memori berisi skema minimal yang
        // dibaca model Source*.
        config(['database.connections.source' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('source');
        $this->createSourceSchema();
    }

    protected function createSourceSchema(): void
    {
        $schema = Schema::connection('source');

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('role')->default('user');
            $table->timestamps();
        });
        $schema->create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('gender')->nullable();
            $table->string('occupation')->nullable();
            $table->integer('budget_min')->nullable();
            $table->integer('budget_max')->nullable();
            $table->json('preferred_facilities')->nullable();
            $table->json('preferred_rules')->nullable();
            $table->string('preferred_location')->nullable();
            $table->timestamps();
        });
        $schema->create('koses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('price');
            $table->string('gender_type');
            $table->string('location')->default('Karawaci');
            $table->double('distance_to_campus')->default(1);
            $table->timestamps();
        });
        foreach (['facilities', 'rules'] as $master) {
            $schema->create($master, function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }
        $schema->create('kos_facility', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kos_id');
            $table->unsignedBigInteger('facility_id');
        });
        $schema->create('kos_rule', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kos_id');
            $table->unsignedBigInteger('rule_id');
        });
        $schema->create('user_interactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('kos_id');
            $table->integer('rating')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->integer('click_count')->default(0);
            $table->timestamps();
        });
        $schema->create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('kos_id');
            $table->date('start_date')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }
}
