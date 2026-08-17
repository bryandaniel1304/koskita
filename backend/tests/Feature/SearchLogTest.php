<?php

namespace Tests\Feature;

use App\Models\Kos;
use App\Models\SearchLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_logs_a_filtered_search_with_zero_results(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/kos?location=KotaHantuTanpaKos');

        $response->assertOk();
        $this->assertDatabaseHas('search_logs', ['location' => 'KotaHantuTanpaKos']);
    }

    public function test_api_does_not_log_browsing_without_any_filter(): void
    {
        $user = User::factory()->create();
        Kos::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/kos')->assertOk();

        $this->assertDatabaseCount('search_logs', 0);
    }

    public function test_api_does_not_log_a_filtered_search_that_actually_found_results(): void
    {
        $user = User::factory()->create();
        Kos::factory()->create(['location' => 'Karawaci']);

        $this->actingAs($user, 'sanctum')->getJson('/api/kos?location=Karawaci')->assertOk();

        $this->assertDatabaseCount('search_logs', 0);
    }

    public function test_web_logs_a_filtered_search_with_zero_results(): void
    {
        $this->get('/kos?search=KosYangTidakPernahAda')->assertOk();

        $this->assertDatabaseHas('search_logs', ['keyword' => 'KosYangTidakPernahAda']);
    }

    public function test_logged_in_users_search_is_attributed_to_them(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/kos?budget_min=999999999')->assertOk();

        $this->assertDatabaseHas('search_logs', ['user_id' => $user->id]);
    }

    public function test_admin_search_log_page_renders(): void
    {
        SearchLog::create(['keyword' => 'kos murah', 'location' => 'Karawaci']);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/pencarian-nihil');

        $response->assertOk()->assertSee('kos murah');
    }
}
