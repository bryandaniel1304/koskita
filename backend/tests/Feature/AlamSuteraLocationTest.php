<?php

namespace Tests\Feature;

use App\Models\Kos;
use App\Models\User;
use App\Services\OnlineNannyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlamSuteraLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_relabels_only_serpong_kos_in_alam_sutera(): void
    {
        $alsut = Kos::factory()->create(['name' => 'Kost Prominence Alam Sutera', 'location' => 'Serpong']);
        $byAddress = Kos::factory()->create(['name' => 'Kost Mila', 'description' => 'Alamat: Jl. Alam Sutera Boulevard.', 'location' => 'Serpong']);
        $serpong = Kos::factory()->create(['name' => 'Kost Gading Serpong', 'description' => 'Dekat SMS', 'location' => 'Serpong']);
        $karawaci = Kos::factory()->create(['name' => 'Kost Alam Sutera Karawaci', 'location' => 'Karawaci']);

        $migration = require database_path('migrations/2026_10_29_100000_label_alam_sutera_koses.php');
        $migration->up();

        $this->assertSame('Alam Sutera', $alsut->fresh()->location);
        $this->assertSame('Alam Sutera', $byAddress->fresh()->location);
        $this->assertSame('Serpong', $serpong->fresh()->location);
        $this->assertSame('Karawaci', $karawaci->fresh()->location, 'hanya kos berlabel Serpong yang dipindah');

        $migration->down();
        $this->assertSame('Serpong', $alsut->fresh()->location);
    }

    public function test_online_nanny_understands_alam_sutera(): void
    {
        $kos = Kos::factory()->create(['name' => 'Kost Alam Sutera Olivia', 'location' => 'Alam Sutera', 'price' => 1500000]);
        Kos::factory()->create(['name' => 'Kost Serpong Lain', 'location' => 'Serpong', 'price' => 1200000]);
        $user = User::factory()->create(['role' => 'user']);

        $result = app(OnlineNannyService::class)->respond($user, 'kos murah di alam sutera serpong');

        $this->assertStringContainsString('Alam Sutera', $result['reply']);
        $this->assertSame([$kos->id], array_column($result['kos'], 'id'));
    }
}
