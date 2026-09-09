<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Survey;
use App\Models\SurveyVersion;
use App\Models\User;
use App\Services\DataImport\ImportValidatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExcelImportFlowTest extends TestCase
{
    use RefreshDatabase;

    protected ImportValidatorService $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ImportValidatorService();
    }

    public function test_validator_transforms_percentages_and_checks_ranges(): void
    {
        $mapping = [
            'survey_id' => 'A',
            'nps_score' => 'B',
            'csat_score' => 'C',
            'professionalism_score' => 'D',
            'agent_bms' => 'E',
            'supervisor' => 'F',
            'survey_date' => 'G',
        ];

        // CSAT is '95' in Excel, should convert to 0.95
        $row = [
            'A' => 'SRV_1001',
            'B' => '0.8',
            'C' => '95',
            'D' => '0.9',
            'E' => 'BMS_4412',
            'F' => 'Carlos Gomez',
            'G' => '2026-09-05',
        ];

        $res = $this->validator->validateRow($row, $mapping, ['csat_score' => ['percentage' => true]]);

        $this->assertTrue($res['valid']);
        $this->assertEquals(0.95, $res['normalized']['csat_score']);
        $this->assertEquals('SRV_1001', $res['normalized']['survey_id']);
    }

    public function test_validator_rejects_out_of_range_nps(): void
    {
        $mapping = [
            'survey_id' => 'A',
            'nps_score' => 'B',
            'csat_score' => 'C',
            'professionalism_score' => 'D',
            'agent_bms' => 'E',
            'supervisor' => 'F',
            'survey_date' => 'G',
        ];

        $row = [
            'A' => 'SRV_1002',
            'B' => '1.5', // Out of range [-1, 1]
            'C' => '0.8',
            'D' => '0.9',
            'E' => 'BMS_4412',
            'F' => 'Carlos Gomez',
            'G' => '2026-09-05',
        ];

        $res = $this->validator->validateRow($row, $mapping);

        $this->assertFalse($res['valid']);
        $this->assertStringContainsString('out of allowed range', $res['errors'][0]);
    }

    public function test_validator_allows_negative_scores_down_to_minus_one(): void
    {
        $mapping = [
            'survey_id' => 'A',
            'nps_score' => 'B',
            'csat_score' => 'C',
            'professionalism_score' => 'D',
            'agent_name' => 'E',
            'agent_bms' => 'F',
            'supervisor' => 'G',
            'survey_date' => 'H',
        ];

        $row = [
            'A' => 'SRV_1003',
            'B' => '-0.75',
            'C' => '-0.50',
            'D' => '-0.20',
            'E' => 'Ana Morales',
            'F' => 'BMS_9999',
            'G' => 'Carlos Gomez',
            'H' => '2026-09-05',
        ];

        $res = $this->validator->validateRow($row, $mapping);

        $this->assertTrue($res['valid']);
        $this->assertEquals(-0.75, $res['normalized']['nps_score']);
        $this->assertEquals(-0.50, $res['normalized']['csat_score']);
        $this->assertEquals(-0.20, $res['normalized']['professionalism_score']);
        $this->assertEquals('Ana Morales', $res['normalized']['agent_name']);
        $this->assertEquals('BMS_9999', $res['normalized']['agent_bms']);
    }

    public function test_validator_rejects_csat_and_prof_below_minus_one(): void
    {
        $mapping = [
            'survey_id' => 'A',
            'nps_score' => 'B',
            'csat_score' => 'C',
            'professionalism_score' => 'D',
            'agent_bms' => 'E',
            'supervisor' => 'F',
            'survey_date' => 'G',
        ];

        $row = [
            'A' => 'SRV_1004',
            'B' => '0.5',
            'C' => '-1.2', // Below -1.0
            'D' => '1.5',  // Above 1.0
            'E' => 'BMS_4412',
            'F' => 'Carlos Gomez',
            'G' => '2026-09-05',
        ];

        $res = $this->validator->validateRow($row, $mapping);

        $this->assertFalse($res['valid']);
        $this->assertCount(2, $res['errors']);
        $this->assertStringContainsString('CSAT Score (-1.2) is out of allowed range [-1, 1]', $res['errors'][0]);
        $this->assertStringContainsString('Professionalism Score (1.5) is out of allowed range [-1, 1]', $res['errors'][1]);
    }

    public function test_idempotent_ingestion_and_versioning(): void
    {
        $import = Import::create([
            'original_filename' => 'test_idempotent.xlsx',
            'file_hash' => 'hash_test',
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        $initialData = [
            'survey_id' => 'SRV_IDEM_1',
            'nps_score' => 0.5,
            'csat_score' => 0.8,
            'professionalism_score' => 0.9,
            'agent_bms' => 'BMS_1',
            'supervisor' => 'SUP_1',
            'survey_date' => '2026-09-01',
        ];
        $initialHash = Survey::computeHash($initialData);

        // 1. Initial Insert
        $survey = Survey::create(array_merge($initialData, [
            'record_hash' => $initialHash,
            'import_id' => $import->id,
        ]));

        $this->assertDatabaseHas('surveys', ['survey_id' => 'SRV_IDEM_1']);

        // 2. Same ID + Same Hash -> Unchanged
        $sameHash = Survey::computeHash($initialData);
        $this->assertEquals($initialHash, $sameHash);

        // 3. Same ID + Different Hash -> Versioned update
        $updatedData = array_merge($initialData, ['nps_score' => 0.9]);
        $newHash = Survey::computeHash($updatedData);
        $this->assertNotEquals($initialHash, $newHash);

        SurveyVersion::create([
            'survey_id' => 'SRV_IDEM_1',
            'previous_hash' => $survey->record_hash,
            'new_hash' => $newHash,
            'import_id' => $import->id,
            'changed_by' => null,
            'diff' => ['nps_score' => 0.9],
        ]);
        $survey->update(array_merge($updatedData, ['record_hash' => $newHash]));

        $this->assertEquals(1, SurveyVersion::where('survey_id', 'SRV_IDEM_1')->count());
        $this->assertEquals(0.9, Survey::where('survey_id', 'SRV_IDEM_1')->first()->nps_score);
    }

    public function test_can_delete_import_and_cascade_surveys(): void
    {
        $permView = \App\Models\Permission::create(['name' => 'Data View', 'slug' => 'data.view']);
        $permDel = \App\Models\Permission::create(['name' => 'Data Delete', 'slug' => 'data.delete']);
        $role = \App\Models\Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $role->permissions()->attach([$permView->id, $permDel->id]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $this->actingAs($user);

        $import = Import::create([
            'original_filename' => 'to_delete.xlsx',
            'file_hash' => 'hash_del',
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        Survey::create([
            'survey_id' => 'SRV_DEL_1',
            'nps_score' => 0.5,
            'csat_score' => 0.8,
            'professionalism_score' => 0.9,
            'agent_bms' => 'BMS_1',
            'supervisor' => 'SUP_1',
            'survey_date' => '2026-09-01',
            'record_hash' => 'hash_del_srv',
            'import_id' => $import->id,
        ]);

        $this->assertDatabaseHas('surveys', ['survey_id' => 'SRV_DEL_1']);

        $res = $this->deleteJson("/data/imports/{$import->id}");
        $res->assertOk();
        $this->assertDatabaseMissing('surveys', ['survey_id' => 'SRV_DEL_1']);
        $this->assertDatabaseMissing('imports', ['id' => $import->id]);
    }

    public function test_categorization_status_endpoint(): void
    {
        $permView = \App\Models\Permission::create(['name' => 'Data View', 'slug' => 'data.view']);
        $role = \App\Models\Role::create(['name' => 'Viewer', 'slug' => 'viewer']);
        $role->permissions()->attach($permView->id);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $this->actingAs($user);

        $res = $this->getJson('/data/categorization-status');
        $res->assertOk();
        $res->assertJsonStructure([
            'total_surveys',
            'total_verbatims',
            'completed',
            'failed',
            'pending',
            'percentage',
            'is_processing',
        ]);
    }
}
