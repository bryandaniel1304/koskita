<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SusDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_sus_results_are_read_from_the_koskita_server_table(): void
    {
        foreach ([[100.0, 'Ani'], [70.0, 'Budi']] as [$score, $name]) {
            DB::connection('source')->table('sus_responses')->insert([
                'respondent_name' => $name,
                'respondent_email' => strtolower($name) . '@example.com',
                'q1' => 5, 'q2' => 1, 'q3' => 5, 'q4' => 1, 'q5' => 5,
                'q6' => 1, 'q7' => 5, 'q8' => 1, 'q9' => 5, 'q10' => 1,
                'sus_score' => $score,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->get('/sus')
            ->assertOk()
            ->assertSee('Ani')
            ->assertSee('Budi')
            ->assertSee('85.0');
    }

    public function test_old_local_form_redirects_to_the_official_questionnaire(): void
    {
        config(['services.koskita.questionnaire_url' => 'https://koskita.example/kuesioner']);

        $this->get('/sus/isi')->assertRedirect('https://koskita.example/kuesioner');
    }
}
