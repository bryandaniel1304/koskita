<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Kos;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MergeDuplicateKosesTest extends TestCase
{
    use RefreshDatabase;

    protected function runMigration(): void
    {
        $migration = require database_path('migrations/2026_10_30_100000_merge_duplicate_koses.php');
        $migration->up();
    }

    public function test_duplicate_listings_become_one_kos_with_user_data_kept(): void
    {
        $keep = Kos::factory()->create(['name' => 'Kost Jln Permata Indah 1 blok B2 No.2 Tipe A Karawaci', 'price' => 3000000, 'gender_type' => 'campur']);
        $dup = Kos::factory()->create(['name' => 'Kost Jln Permata Indah 1 Blok B2 No.2 Curug Tangerang', 'price' => 4500000, 'gender_type' => 'campur']);
        $other = Kos::factory()->create(['name' => 'Kost Lain']);

        $ac = Facility::create(['name' => 'AC']);
        $wifi = Facility::create(['name' => 'WiFi']);
        $keep->facilities()->attach($ac);
        $dup->facilities()->attach([$ac->id, $wifi->id]);

        $both = User::factory()->create(['role' => 'user']);
        $onlyDup = User::factory()->create(['role' => 'user']);
        UserInteraction::create(['user_id' => $both->id, 'kos_id' => $keep->id, 'click_count' => 2]);
        UserInteraction::create(['user_id' => $both->id, 'kos_id' => $dup->id, 'rating' => 4, 'is_favorite' => true, 'click_count' => 3]);
        UserInteraction::create(['user_id' => $onlyDup->id, 'kos_id' => $dup->id, 'rating' => 5]);

        $this->runMigration();

        $this->assertNull(Kos::find($dup->id));
        $this->assertNotNull(Kos::find($other->id));
        $kept = Kos::with('facilities')->find($keep->id);
        $this->assertSame(3000000, (int) $kept->price);
        $this->assertEqualsCanonicalizing(['AC', 'WiFi'], $kept->facilities->pluck('name')->all());

        $merged = UserInteraction::where('user_id', $both->id)->where('kos_id', $keep->id)->sole();
        $this->assertSame(4, $merged->rating);
        $this->assertTrue((bool) $merged->is_favorite);
        $this->assertSame(5, $merged->click_count);
        $this->assertSame(5, UserInteraction::where('user_id', $onlyDup->id)->where('kos_id', $keep->id)->value('rating'));
        $this->assertSame(0, DB::table('user_interactions')->where('kos_id', $dup->id)->count());
    }

    public function test_specific_gender_wins_over_campur(): void
    {
        $keep = Kos::factory()->create(['name' => 'Rumah Kost AC 36', 'price' => 1600000, 'gender_type' => 'campur']);
        Kos::factory()->create(['name' => 'Rumah Kost Khusus Pria', 'price' => 1600000, 'gender_type' => 'putra']);

        $this->runMigration();

        $this->assertSame('putra', $keep->fresh()->gender_type);
        $this->assertSame(1, Kos::where('name', 'like', 'Rumah Kost%')->count());
    }

    public function test_incomplete_group_is_left_alone(): void
    {
        Kos::factory()->create(['name' => 'Kos De Noveran']);

        $this->runMigration();

        $this->assertSame(1, Kos::where('name', 'Kos De Noveran')->count());
    }
}
