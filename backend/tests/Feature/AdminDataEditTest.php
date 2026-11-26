<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Kos;
use App\Models\SusResponse;
use App\Models\User;
use App\Models\UserInteraction;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master admin bisa mengoreksi/menghapus jawaban SUS dan data responden
 * (akun + profil preferensi + interaksi) dari panel admin; admin biasa tidak.
 */
class AdminDataEditTest extends TestCase
{
    use RefreshDatabase;

    private function masterAdmin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_master_admin' => true]);
    }

    private function sus(array $overrides = []): SusResponse
    {
        $answers = [];
        foreach (range(1, 10) as $n) {
            $answers["q$n"] = 3;
        }

        return SusResponse::create([
            'respondent_name' => 'Rina',
            'respondent_email' => 'rina@example.com',
            ...$answers,
            ...$overrides,
        ]);
    }

    private function susPayload(int $value, array $overrides = []): array
    {
        $payload = ['respondent_name' => 'Rina Koreksi', 'respondent_email' => 'rina@example.com', 'feedback' => 'ok'];
        foreach (range(1, 10) as $n) {
            $payload["q$n"] = $n % 2 === 1 ? $value : 6 - $value;
        }

        return [...$payload, ...$overrides];
    }

    public function test_master_admin_can_edit_sus_answer_and_score_is_recalculated(): void
    {
        $admin = $this->masterAdmin();
        $response = $this->sus();
        $this->assertSame(50.0, $response->sus_score);

        $this->actingAs($admin)->get('/admin/kuesioner-sus')->assertOk()->assertSee('Hapus yang dipilih');
        $this->actingAs($admin)->get("/admin/kuesioner-sus/{$response->id}/edit")->assertOk()->assertSee('Rina');

        $this->actingAs($admin)
            ->put("/admin/kuesioner-sus/{$response->id}", $this->susPayload(5))
            ->assertRedirect('/admin/kuesioner-sus');

        $response->refresh();
        $this->assertSame('Rina Koreksi', $response->respondent_name);
        $this->assertSame(100.0, $response->sus_score);

        $csv = $this->actingAs($admin)->get('/admin/kuesioner-sus/export')->streamedContent();
        $this->assertStringNotContainsString('Diubah Admin', $csv);
        $this->assertStringContainsString('Rina Koreksi', $csv);
    }

    public function test_sus_answer_out_of_range_is_rejected(): void
    {
        $response = $this->sus();

        $this->actingAs($this->masterAdmin())
            ->put("/admin/kuesioner-sus/{$response->id}", $this->susPayload(3, ['q4' => 7]))
            ->assertSessionHasErrors('q4');

        $this->assertSame(50.0, $response->fresh()->sus_score);
    }

    public function test_master_admin_can_delete_single_and_bulk_sus_answers(): void
    {
        $admin = $this->masterAdmin();
        [$a, $b, $c] = [$this->sus(), $this->sus(), $this->sus()];

        $this->actingAs($admin)->delete("/admin/kuesioner-sus/{$a->id}")->assertRedirect('/admin/kuesioner-sus');
        $this->actingAs($admin)->delete('/admin/kuesioner-sus', ['ids' => [$b->id, $c->id]])->assertRedirect('/admin/kuesioner-sus');

        $this->assertSame(0, SusResponse::count());
    }

    public function test_regular_admin_cannot_edit_or_delete_sus_answers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->sus();

        $this->actingAs($admin)->get('/admin/kuesioner-sus')->assertOk()->assertDontSee('Hapus yang dipilih');
        $this->actingAs($admin)->put("/admin/kuesioner-sus/{$response->id}", $this->susPayload(5))->assertForbidden();
        $this->actingAs($admin)->delete("/admin/kuesioner-sus/{$response->id}")->assertForbidden();

        $this->assertSame(50.0, $response->fresh()->sus_score);
    }

    public function test_master_admin_can_edit_respondent_account_and_profile(): void
    {
        $admin = $this->masterAdmin();
        Facility::create(['name' => 'WiFi']);
        $user = User::factory()->create(['role' => 'user', 'name' => 'Lama']);
        UserProfile::create([
            'user_id' => $user->id, 'gender' => 'pria', 'occupation' => 'mahasiswa',
            'budget_min' => 1000000, 'budget_max' => 3000000,
            'preferred_facilities' => [], 'preferred_rules' => [], 'preferred_location' => 'Karawaci',
        ]);

        $this->actingAs($admin)->get("/admin/users/{$user->id}/edit")->assertOk()->assertSee('WiFi');

        $this->actingAs($admin)->put("/admin/users/{$user->id}/data", [
            'name' => 'Baru',
            'email' => 'baru@example.com',
            'phone' => '0812',
            'gender' => 'wanita',
            'occupation' => 'pekerja',
            'preferred_location' => 'BSD',
            'budget_min' => 1500000,
            'budget_max' => 2500000,
            'preferred_facilities' => ['WiFi'],
        ])->assertRedirect("/admin/users/{$user->id}");

        $user->refresh();
        $this->assertSame('Baru', $user->name);
        $this->assertSame('baru@example.com', $user->email);
        $this->assertSame('wanita', $user->profile->gender);
        $this->assertSame('BSD', $user->profile->preferred_location);
        $this->assertSame(['WiFi'], $user->profile->preferred_facilities);
        $this->assertSame([], $user->profile->preferred_rules);
        $this->assertNotNull($user->profile->completed_at);
    }

    public function test_respondent_budget_and_email_are_validated(): void
    {
        $admin = $this->masterAdmin();
        User::factory()->create(['email' => 'dipakai@example.com']);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)->put("/admin/users/{$user->id}/data", [
            'name' => 'X', 'email' => 'dipakai@example.com',
            'gender' => 'pria', 'occupation' => 'mahasiswa', 'preferred_location' => 'Karawaci',
            'budget_min' => 3000000, 'budget_max' => 1000000,
        ])->assertSessionHasErrors(['email', 'budget_max']);
    }

    public function test_owner_edit_only_touches_account_fields(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($this->masterAdmin())->put("/admin/users/{$owner->id}/data", [
            'name' => 'Pemilik Baru', 'email' => 'pemilik@example.com', 'phone' => '0813',
        ])->assertRedirect("/admin/users/{$owner->id}");

        $this->assertSame('Pemilik Baru', $owner->fresh()->name);
        $this->assertNull(UserProfile::where('user_id', $owner->id)->first());
    }

    public function test_master_admin_can_delete_interaction_of_that_user_only(): void
    {
        $admin = $this->masterAdmin();
        $kos = Kos::factory()->create();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = UserInteraction::create(['user_id' => $user->id, 'kos_id' => $kos->id, 'rating' => 4]);
        $theirs = UserInteraction::create(['user_id' => $other->id, 'kos_id' => $kos->id, 'rating' => 5]);

        $this->actingAs($admin)->delete("/admin/users/{$user->id}/interaksi/{$theirs->id}")->assertNotFound();
        $this->actingAs($admin)->delete("/admin/users/{$user->id}/interaksi/{$mine->id}")->assertRedirect();

        $this->assertNull($mine->fresh());
        $this->assertNotNull($theirs->fresh());
    }
}
