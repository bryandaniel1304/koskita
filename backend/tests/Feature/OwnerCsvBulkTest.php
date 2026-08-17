<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OwnerCsvBulkTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): User
    {
        return User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    }

    public function test_owner_can_export_own_koses_as_csv(): void
    {
        $owner = $this->owner();
        Kos::factory()->create(['owner_id' => $owner->id, 'name' => 'Kos Ekspor Satu']);
        Kos::factory()->create(['owner_id' => $this->owner()->id, 'name' => 'Kos Milik Lain']);

        $response = $this->actingAs($owner)->get('/pemilik/kos/ekspor');

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Kos Ekspor Satu', $content);
        $this->assertStringNotContainsString('Kos Milik Lain', $content);
    }

    public function test_owner_can_bulk_update_price_and_rooms_via_csv(): void
    {
        $owner = $this->owner();
        $kos = Kos::factory()->create(['owner_id' => $owner->id, 'price' => 1000000, 'total_rooms' => 5]);

        $csv = "id,nama,harga,total_kamar,kamar_terisi,lokasi,tipe\n{$kos->id},Kos,2000000,8,0,Karawaci,putra\n";
        $file = UploadedFile::fake()->createWithContent('kos.csv', $csv);

        $response = $this->actingAs($owner)->post('/pemilik/kos/impor', ['file' => $file]);

        $response->assertRedirect();
        $kos->refresh();
        $this->assertSame(2000000, $kos->price);
        $this->assertSame(8, $kos->total_rooms);
    }

    public function test_csv_import_cannot_touch_other_owners_kos(): void
    {
        $owner = $this->owner();
        $otherOwner = $this->owner();
        $kos = Kos::factory()->create(['owner_id' => $otherOwner->id, 'price' => 1000000]);

        $csv = "id,nama,harga,total_kamar,kamar_terisi,lokasi,tipe\n{$kos->id},Kos,9999999,10,0,Karawaci,putra\n";
        $file = UploadedFile::fake()->createWithContent('kos.csv', $csv);

        $this->actingAs($owner)->post('/pemilik/kos/impor', ['file' => $file]);

        $this->assertSame(1000000, $kos->fresh()->price);
    }

    public function test_csv_import_rejects_rooms_below_occupied_count(): void
    {
        $owner = $this->owner();
        $kos = Kos::factory()->create(['owner_id' => $owner->id, 'total_rooms' => 5]);
        Booking::create([
            'user_id' => User::factory()->create()->id,
            'kos_id' => $kos->id,
            'start_date' => now(),
            'duration_months' => 3,
            'status' => 'confirmed',
        ]);

        $csv = "id,nama,harga,total_kamar,kamar_terisi,lokasi,tipe\n{$kos->id},Kos,1000000,0,1,Karawaci,putra\n";
        $file = UploadedFile::fake()->createWithContent('kos.csv', $csv);

        $response = $this->actingAs($owner)->post('/pemilik/kos/impor', ['file' => $file]);

        $response->assertSessionHasErrors('import');
        $this->assertSame(5, $kos->fresh()->total_rooms);
    }
}
