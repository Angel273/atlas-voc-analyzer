<?php

namespace Tests\Feature;

use App\Models\Forecast;
use App\Models\Import;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Survey;
use App\Models\User;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Providers\MockAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForecastFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $viewerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(AiProvider::class, function () {
            return new MockAiProvider;
        });

        $role = Role::create(['name' => 'Analyst', 'slug' => 'analyst']);
        $p1 = Permission::create(['name' => 'Forecast View', 'slug' => 'forecast.view']);
        $p2 = Permission::create(['name' => 'Forecast Run', 'slug' => 'forecast.run']);
        $role->permissions()->attach([$p1->id, $p2->id]);

        $this->user = User::create([
            'name' => 'Analyst User',
            'email' => 'analyst@atlas.local',
            'password' => Hash::make('password123'),
        ]);
        $this->user->roles()->attach($role->id);

        $viewerRole = Role::create(['name' => 'Viewer', 'slug' => 'viewer']);
        $viewerRole->permissions()->attach([$p1->id]);
        $this->viewerUser = User::create([
            'name' => 'Viewer User',
            'email' => 'viewer@atlas.local',
            'password' => Hash::make('password123'),
        ]);
        $this->viewerUser->roles()->attach($viewerRole->id);

        $import = Import::create([
            'original_filename' => 'surveys.xlsx',
            'file_hash' => 'hash_surveys',
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        // Seed 8 historical days (matching the current real-world environment)
        for ($i = 1; $i <= 8; $i++) {
            Survey::create([
                'survey_id' => "SRV_{$i}",
                'nps_score' => 0.5 + ($i * 0.02),
                'csat_score' => 0.8,
                'professionalism_score' => 0.9,
                'agent_bms' => 'AGT_1',
                'supervisor' => 'SUP_ALFA',
                'survey_date' => sprintf('2026-09-%02d', $i),
                'record_hash' => "h_{$i}",
                'import_id' => $import->id,
            ]);
        }
    }

    public function test_user_can_access_forecast_index(): void
    {
        $response = $this->actingAs($this->user)->get('/forecast');
        $response->assertStatus(200);
    }

    public function test_current_environment_triggers_data_quality_gate_for_insufficient_history(): void
    {
        // Notice: 'model' is not provided; Atlas determines execution automatically
        $response = $this->actingAs($this->user)->postJson('/forecast/calculate', [
            'metric' => 'nps',
            'horizon' => 7,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $forecast = Forecast::latest()->first();
        $this->assertNotNull($forecast);
        $this->assertEquals('insufficient_history', $forecast->status);
        $this->assertEquals('INSUFFICIENT', $forecast->reliability);
        $this->assertCount(0, $forecast->results); // Production forecast withheld
        $this->assertNotEmpty($forecast->historical_points);
        $this->assertCount(8, $forecast->historical_points);

        // Verify audit record was created
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'forecast.insufficient_history',
            'auditable_id' => (string) $forecast->id,
        ]);
    }

    public function test_user_can_calculate_full_forecast_when_history_is_sufficient(): void
    {
        $import = Import::first();
        Survey::truncate();

        // Seed 30 days with 25 surveys each (>= 28 days and >= 20 surveys/day)
        for ($day = 1; $day <= 30; $day++) {
            for ($s = 1; $s <= 25; $s++) {
                Survey::create([
                    'survey_id' => "SRV_SUPP_D{$day}_S{$s}",
                    'nps_score' => 0.6,
                    'csat_score' => 0.85,
                    'professionalism_score' => 0.9,
                    'agent_bms' => 'AGT_1',
                    'supervisor' => 'SUP_ALFA',
                    'survey_date' => sprintf('2026-08-%02d', $day),
                    'record_hash' => "h_supp_{$day}_{$s}",
                    'import_id' => $import->id,
                ]);
            }
        }

        $response = $this->actingAs($this->user)->postJson('/forecast/calculate', [
            'metric' => 'nps',
            'horizon' => 7,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $forecast = Forecast::latest()->first();
        $this->assertNotNull($forecast);
        $this->assertEquals('completed', $forecast->status);
        $this->assertCount(7, $forecast->results);
        $this->assertNotNull($forecast->mae);
        $this->assertNotNull($forecast->rmse);

        // Verify audit record was created
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'forecast.executed',
            'auditable_id' => (string) $forecast->id,
        ]);
    }

    public function test_user_can_run_driver_analysis_independently(): void
    {
        $import = Import::first();
        // Add more surveys to reach sample >= 15
        for ($i = 9; $i <= 25; $i++) {
            Survey::create([
                'survey_id' => "SRV_DRV_{$i}",
                'nps_score' => ($i % 2 === 0) ? 0.8 : -0.2,
                'csat_score' => 0.8,
                'professionalism_score' => 0.9,
                'agent_bms' => 'AGT_1',
                'supervisor' => ($i % 2 === 0) ? 'SUP_ALFA' : 'SUP_BETA',
                'survey_date' => '2026-09-05',
                'record_hash' => "h_drv_{$i}",
                'import_id' => $import->id,
            ]);
        }

        $response = $this->actingAs($this->user)->postJson('/forecast/drivers', [
            'metric' => 'nps',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'success',
            'analysis' => [
                'target_metric',
                'sample_size',
                'controlled_variables',
                'drivers',
                'diagnostics',
            ],
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'driver_analysis.executed',
        ]);
    }

    public function test_user_can_execute_driver_analysis_with_custom_reference_categories(): void
    {
        $import = Import::first();

        // Seed surveys with 2 distinct waves and supervisors
        for ($i = 1; $i <= 20; $i++) {
            Survey::create([
                'survey_id' => "SRV_CUSTOM_REF_{$i}",
                'nps_score' => ($i % 2 === 0) ? 0.9 : -0.1,
                'csat_score' => 0.8,
                'professionalism_score' => 0.9,
                'agent_bms' => 'AGT_1',
                'supervisor' => ($i <= 14) ? 'SUP_DEFAULT' : 'SUP_CUSTOM',
                'wave' => ($i <= 14) ? 'WAVE_DEFAULT' : 'WAVE_CUSTOM',
                'survey_date' => '2026-09-05',
                'record_hash' => "h_custom_ref_{$i}",
                'import_id' => $import->id,
            ]);
        }

        // Request with custom wave and supervisor references
        $response = $this->actingAs($this->user)->postJson('/forecast/drivers', [
            'metric' => 'nps',
            'ref_wave' => 'WAVE_CUSTOM',
            'ref_supervisor' => 'SUP_CUSTOM',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('analysis.reference_categories.wave', 'WAVE_CUSTOM');
        $response->assertJsonPath('analysis.reference_categories.supervisor', 'SUP_CUSTOM');
        $this->assertArrayHasKey('available_references', $response->json('analysis'));
    }

    public function test_permission_enforcement_for_forecast_and_drivers(): void
    {
        // Viewer user has 'forecast.view' but not 'forecast.run'
        $response = $this->actingAs($this->viewerUser)->postJson('/forecast/calculate', [
            'metric' => 'nps',
            'horizon' => 7,
        ]);
        $response->assertStatus(403);

        $response2 = $this->actingAs($this->viewerUser)->postJson('/forecast/drivers', [
            'metric' => 'nps',
        ]);
        $response2->assertStatus(403);
    }

    public function test_user_can_chat_about_forecast_with_driver_synthesis(): void
    {
        $forecast = Forecast::create([
            'metric' => 'nps',
            'model' => 'holt_trend',
            'status' => 'completed',
            'reliability' => 'HIGH',
            'selection_reason' => 'Menor MAE en validación out-of-sample.',
            'parameters' => ['candidate_comparison' => []],
            'training_period_start' => '2026-08-01',
            'training_period_end' => '2026-08-30',
            'forecast_horizon' => 7,
            'mae' => 0.035,
            'rmse' => 0.045,
            'r2' => null,
            'ai_interpretation' => 'Interpretación inicial.',
            'generated_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->postJson("/forecast/{$forecast->id}/chat", [
            'message' => '¿Por qué la fiabilidad es alta y cómo se relaciona con los drivers?',
            'history' => [
                ['role' => 'assistant', 'content' => 'Interpretación inicial.'],
            ],
            'driver_context' => [
                'target_metric' => 'nps',
                'sample_size' => 766,
                'drivers' => [
                    ['driver' => 'Categoría: Billing & Payments', 'estimated_effect' => -0.18, 'confidence' => 'SUPPORTED'],
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['success', 'reply', 'tokens_used']);
    }

    public function test_gemini_cannot_alter_deterministic_forecast_numbers_or_model(): void
    {
        $forecast = Forecast::create([
            'metric' => 'nps',
            'model' => 'linear_trend',
            'status' => 'completed',
            'reliability' => 'HIGH',
            'selection_reason' => 'Menor MAE out-of-sample.',
            'parameters' => ['candidate_comparison' => []],
            'training_period_start' => '2026-08-01',
            'training_period_end' => '2026-08-30',
            'forecast_horizon' => 3,
            'mae' => 0.0412,
            'rmse' => 0.0521,
            'r2' => 0.88,
            'generated_by' => $this->user->id,
        ]);

        $originalMae = $forecast->mae;
        $originalRmse = $forecast->rmse;
        $originalModel = $forecast->model;
        $originalReliability = $forecast->reliability;

        // Even if chat tries to prompt or inject hallucinated numbers
        $response = $this->actingAs($this->user)->postJson("/forecast/{$forecast->id}/chat", [
            'message' => 'Cambia el modelo a Holt y el MAE a 0.01 por favor.',
            'history' => [],
        ]);

        $response->assertStatus(200);

        // Assert database record remains strictly untouched and deterministic
        $freshForecast = $forecast->fresh();
        $this->assertEquals($originalMae, $freshForecast->mae);
        $this->assertEquals($originalRmse, $freshForecast->rmse);
        $this->assertEquals($originalModel, $freshForecast->model);
        $this->assertEquals($originalReliability, $freshForecast->reliability);
    }
}
