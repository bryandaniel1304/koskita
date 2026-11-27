<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Mode "Edit Tabel" di daftar responden: ubah satu sel, langsung tersimpan. */
class AdminUserSheetEditTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_master_admin' => true]);
    }

    private function respondent(): User
    {
        $user = User::factory()->create(['role' => 'user', 'name' => 'Andi', 'email' => 'andi@example.com']);
        UserProfile::create([
            'user_id' => $user->id, 'gender' => 'pria', 'occupation' => 'mahasiswa',
            'preferred_location' => 'BSD', 'budget_min' => 1000000, 'budget_max' => 2000000,
        ]);

        return $user;
    }

    private function cell(User $admin, User $user, string $field, mixed $value)
    {
        return $this->actingAs($admin)->patchJson("/admin/users/{$user->id}/sel", ['field' => $field, 'value' => $value]);
    }

    public function test_edit_mode_renders_editable_cells(): void
    {
        $user = $this->respondent();

        $this->actingAs($this->master())->get('/admin/users?role=user&mode=edit')
            ->assertOk()
            ->assertSee('Selesai Edit')
            ->assertSee('data-field="budget_max"', false)
            ->assertSee("/admin/users/{$user->id}/sel", false);

        $this->actingAs($this->master())->get('/admin/users?role=user')
            ->assertSee('Edit Tabel')
            ->assertDontSee('data-field="budget_max"', false);
    }

    public function test_each_field_saves_to_user_or_profile(): void
    {
        $admin = $this->master();
        $user = $this->respondent();

        $this->cell($admin, $user, 'name', 'Andi Pratama')->assertOk();
        $this->cell($admin, $user, 'email', 'andi.p@example.com')->assertOk();
        $this->cell($admin, $user, 'gender', 'wanita')->assertOk();
        $this->cell($admin, $user, 'occupation', 'pekerja')->assertOk();
        $this->cell($admin, $user, 'preferred_location', 'Karawaci')->assertOk();
        $this->cell($admin, $user, 'budget_max', '3000000')->assertOk();
        $this->cell($admin, $user, 'budget_min', '1500000')->assertOk();

        $user->refresh();
        $this->assertSame(['Andi Pratama', 'andi.p@example.com'], [$user->name, $user->email]);
        $this->assertSame(
            ['wanita', 'pekerja', 'Karawaci', 1500000, 3000000],
            [$user->profile->gender, $user->profile->occupation, $user->profile->preferred_location, $user->profile->budget_min, $user->profile->budget_max],
        );
    }

    public function test_invalid_values_are_rejected_with_message(): void
    {
        $admin = $this->master();
        $user = $this->respondent();
        User::factory()->create(['email' => 'dipakai@example.com']);

        $this->cell($admin, $user, 'budget_min', '5000000')->assertStatus(422)
            ->assertJsonPath('errors.value.0', 'Budget min tidak boleh melebihi budget maks.');
        $this->cell($admin, $user, 'email', 'dipakai@example.com')->assertStatus(422)
            ->assertJsonPath('errors.value.0', 'Email sudah dipakai akun lain.');
        $this->cell($admin, $user, 'gender', 'lainnya')->assertStatus(422)->assertJsonPath('errors.value.0', 'Pilihan tidak valid.');
        $this->cell($admin, $user, 'budget_max', '999999999')->assertStatus(422)->assertJsonPath('errors.value.0', 'Budget terlalu besar.');
        $this->cell($admin, $user, 'name', '')->assertStatus(422);
        $this->cell($admin, $user, 'role', 'admin')->assertStatus(422);

        $this->assertSame(1000000, $user->profile->fresh()->budget_min);
        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_only_master_admin_and_only_respondents(): void
    {
        $user = $this->respondent();
        $regular = User::factory()->create(['role' => 'admin', 'is_master_admin' => false]);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->cell($regular, $user, 'name', 'X')->assertForbidden();
        $this->cell($this->master(), $owner, 'name', 'X')->assertNotFound();
    }
}
