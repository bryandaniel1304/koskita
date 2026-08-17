<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test untuk export CSV di panel admin (kos, booking, responden) -- fitur
 * pelengkap laporan skripsi. Pakai session guard biasa (bukan sanctum),
 * konsisten dengan cara IsAdmin middleware mengecek Auth::check().
 */
class AdminExportTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_guest_cannot_access_kos_export(): void
    {
        $response = $this->get('/admin/koses/export');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_export_kos_csv(): void
    {
        Kos::factory()->count(2)->create();

        $response = $this->actingAs($this->admin())->get('/admin/koses/export');

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('ID,Nama,Lokasi,Harga', $csv);
    }

    public function test_admin_can_export_bookings_csv(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();
        Booking::create([
            'user_id' => $user->id,
            'kos_id' => $kos->id,
            'start_date' => now()->addDays(3),
            'duration_months' => 6,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin())->get('/admin/bookings/export');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('ID,Penyewa', $csv);
        $this->assertStringContainsString($user->name, $csv);
    }

    public function test_admin_can_export_users_csv_without_leaking_password(): void
    {
        User::factory()->create(['role' => 'user', 'name' => 'Responden Uji']);

        $response = $this->actingAs($this->admin())->get('/admin/users/export');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Responden Uji', $csv);
        $this->assertStringNotContainsString('password', strtolower($csv));
    }
}
