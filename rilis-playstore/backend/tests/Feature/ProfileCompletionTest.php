<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penyewa baru wajib mengisi profil preferensi (Onboarding) sendiri --
 * profil default dari registrasi ditandai belum lengkap (completed_at null)
 * sampai pengguna menyimpan preferensinya lewat POST /api/profile.
 */
class ProfileCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_tenant_profile_starts_incomplete(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Sari Responden',
            'email' => 'sari@example.com',
            'phone' => '081234567890',
            'password' => 'password123',
            'role' => 'user',
        ]);

        $response->assertStatus(201)->assertJsonPath('user.profile.completed_at', null);
    }

    public function test_saving_preferences_marks_profile_complete(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Sari Responden',
            'email' => 'sari@example.com',
            'phone' => '081234567890',
            'password' => 'password123',
            'role' => 'user',
        ]);
        $user = User::where('email', 'sari@example.com')->first();

        $this->actingAs($user, 'sanctum')->postJson('/api/profile', [
            'gender' => 'wanita',
            'occupation' => 'pekerja',
            'budget_min' => 1500000,
            'budget_max' => 2500000,
            'preferred_location' => 'BSD',
        ])->assertOk();

        $this->assertNotNull($user->profile()->first()->completed_at);
        $this->actingAs($user, 'sanctum')->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('profile.preferred_location', 'BSD')
            ->assertJsonPath('profile.completed_at', fn ($value) => $value !== null);
    }
}
