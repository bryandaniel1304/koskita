<?php

namespace Tests\Feature;

use App\Models\SusResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kuesioner SUS publik (/kuesioner) untuk responden uji coba online, dan
 * rekap hasilnya di panel admin (/admin/kuesioner-sus).
 */
class SusQuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    /** Semua pernyataan dijawab 3 kecuali yang ditimpa. */
    private function answers(array $overrides = []): array
    {
        $answers = [];
        foreach (array_keys(SusResponse::QUESTIONS) as $num) {
            $answers["q$num"] = 3;
        }

        return array_merge($answers, $overrides);
    }

    public function test_form_is_publicly_accessible(): void
    {
        $this->get('/kuesioner')->assertOk()->assertSee(SusResponse::QUESTIONS[1]);
    }

    public function test_registered_respondent_can_submit_and_score_is_calculated(): void
    {
        $user = User::factory()->create(['role' => 'user', 'email' => 'responden@example.com']);

        // Jawaban ideal: ganjil 5, genap 1 -> skor maksimum 100.
        $response = $this->post('/kuesioner', [
            'email' => 'responden@example.com',
            'feedback' => 'Tampilannya rapi.',
            ...$this->answers(['q1' => 5, 'q2' => 1, 'q3' => 5, 'q4' => 1, 'q5' => 5, 'q6' => 1, 'q7' => 5, 'q8' => 1, 'q9' => 5, 'q10' => 1]),
        ]);

        $response->assertRedirect(route('sus.thanks'));
        $this->assertDatabaseHas('sus_responses', [
            'user_id' => $user->id,
            'respondent_name' => $user->name,
            'respondent_email' => 'responden@example.com',
            'sus_score' => 100,
            'feedback' => 'Tampilannya rapi.',
        ]);
    }

    public function test_neutral_answers_score_fifty(): void
    {
        User::factory()->create(['email' => 'netral@example.com']);

        $this->post('/kuesioner', ['email' => 'netral@example.com', ...$this->answers()]);

        $this->assertSame(50.0, SusResponse::first()->sus_score);
    }

    public function test_unregistered_email_is_rejected(): void
    {
        $this->from('/kuesioner')
            ->post('/kuesioner', ['email' => 'tidak-ada@example.com', ...$this->answers()])
            ->assertRedirect('/kuesioner')
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('sus_responses', 0);
    }

    public function test_same_account_cannot_submit_twice(): void
    {
        User::factory()->create(['email' => 'dobel@example.com']);
        $this->post('/kuesioner', ['email' => 'dobel@example.com', ...$this->answers()]);

        $this->from('/kuesioner')
            ->post('/kuesioner', ['email' => 'dobel@example.com', ...$this->answers(['q1' => 5])])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('sus_responses', 1);
    }

    public function test_all_ten_statements_are_required(): void
    {
        User::factory()->create(['email' => 'kurang@example.com']);
        $answers = $this->answers();
        unset($answers['q7']);

        $this->post('/kuesioner', ['email' => 'kurang@example.com', ...$answers])
            ->assertSessionHasErrors('q7');

        $this->assertDatabaseCount('sus_responses', 0);
    }

    public function test_admin_can_view_results_and_export_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['name' => 'Rina Responden']);
        SusResponse::create([
            'user_id' => $user->id,
            'respondent_name' => $user->name,
            'respondent_email' => $user->email,
            'feedback' => 'Fitur bandingkan kos membantu.',
            ...$this->answers(),
        ]);

        $this->actingAs($admin)->get('/admin/kuesioner-sus')
            ->assertOk()
            ->assertSee('Rina Responden')
            ->assertSee('Fitur bandingkan kos membantu.');

        $csv = $this->actingAs($admin)->get('/admin/kuesioner-sus/export');
        $csv->assertOk();
        $this->assertStringContainsString('Rina Responden', $csv->streamedContent());
    }

    public function test_results_are_hidden_from_non_admin(): void
    {
        $tenant = User::factory()->create(['role' => 'user']);

        $this->actingAs($tenant)->get('/admin/kuesioner-sus')->assertRedirect('/login');
    }
}
