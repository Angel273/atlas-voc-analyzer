<?php

namespace Tests\Unit;

use App\Models\Import;
use App\Models\Survey;
use App\Services\Privacy\EntityFuzzyMatcher;
use App\Services\Privacy\PseudonymService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityFuzzyMatcherTest extends TestCase
{
    use RefreshDatabase;

    protected EntityFuzzyMatcher $matcher;

    protected PseudonymService $pseudonyms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new EntityFuzzyMatcher;
        $this->pseudonyms = new PseudonymService;

        $import = Import::create([
            'original_filename' => 'seed.xlsx',
            'file_hash' => 'hash_test_1',
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        Survey::create([
            'survey_id' => 'SRV_TEST_1',
            'nps_score' => 1.0,
            'csat_score' => 1.0,
            'professionalism_score' => 1.0,
            'agent_bms' => '4250331',
            'agent_name' => 'Aguilar Marroquin, Fatima A',
            'supervisor' => 'Majano Siliezar, Michael E',
            'survey_date' => '2026-09-01',
            'record_hash' => 'hash_t1',
            'import_id' => $import->id,
        ]);

        Survey::create([
            'survey_id' => 'SRV_TEST_2',
            'nps_score' => 0.5,
            'csat_score' => 0.7,
            'professionalism_score' => 0.8,
            'agent_bms' => '4732167',
            'agent_name' => 'Amaya Aguirre, Jose P',
            'supervisor' => 'Ramirez Pineda, Janeth C',
            'survey_date' => '2026-09-02',
            'record_hash' => 'hash_t2',
            'import_id' => $import->id,
        ]);
    }

    public function test_find_best_supervisor_match_by_first_name(): void
    {
        $match = $this->matcher->findBestSupervisorMatch('michael');
        $this->assertSame('Majano Siliezar, Michael E', $match);

        $matchUpper = $this->matcher->findBestSupervisorMatch('MICHAEL');
        $this->assertSame('Majano Siliezar, Michael E', $matchUpper);

        $matchJaneth = $this->matcher->findBestSupervisorMatch('janeth');
        $this->assertSame('Ramirez Pineda, Janeth C', $matchJaneth);
    }

    public function test_find_best_supervisor_match_by_surname(): void
    {
        $matchMajano = $this->matcher->findBestSupervisorMatch('majano');
        $this->assertSame('Majano Siliezar, Michael E', $matchMajano);

        $matchSiliezar = $this->matcher->findBestSupervisorMatch('siliezar');
        $this->assertSame('Majano Siliezar, Michael E', $matchSiliezar);

        $matchRamirez = $this->matcher->findBestSupervisorMatch('ramirez');
        $this->assertSame('Ramirez Pineda, Janeth C', $matchRamirez);
    }

    public function test_find_best_supervisor_match_by_natural_name_order(): void
    {
        $match = $this->matcher->findBestSupervisorMatch('Michael Majano');
        $this->assertSame('Majano Siliezar, Michael E', $match);

        $matchFull = $this->matcher->findBestSupervisorMatch('Michael E Majano Siliezar');
        $this->assertSame('Majano Siliezar, Michael E', $matchFull);
    }

    public function test_find_best_supervisor_match_with_typos(): void
    {
        // Transposition / vowel typo
        $match = $this->matcher->findBestSupervisorMatch('micheal');
        $this->assertSame('Majano Siliezar, Michael E', $match);

        // Phonetic variation
        $matchMayano = $this->matcher->findBestSupervisorMatch('mayano');
        $this->assertSame('Majano Siliezar, Michael E', $matchMayano);

        // Typo on Janeth
        $matchJanet = $this->matcher->findBestSupervisorMatch('janet');
        $this->assertSame('Ramirez Pineda, Janeth C', $matchJanet);
    }

    public function test_ignores_common_stopwords(): void
    {
        $this->assertNull($this->matcher->findBestSupervisorMatch('equipo'));
        $this->assertNull($this->matcher->findBestSupervisorMatch('supervisor'));
        $this->assertNull($this->matcher->findBestSupervisorMatch('analisis'));
        $this->assertNull($this->matcher->findBestSupervisorMatch('de'));
    }

    public function test_extract_and_replace_entities_in_user_prompt(): void
    {
        $text = 'haz un analisis completo del equipo de michael y de janeth';
        $meta = [];
        $scrubbed = $this->matcher->extractAndReplaceEntities($text, 'scope_test_fuzzy', $this->pseudonyms, $meta);

        $this->assertStringNotContainsString('michael', strtolower($scrubbed));
        $this->assertStringNotContainsString('janeth', strtolower($scrubbed));
        $this->assertStringContainsString('SUP_', $scrubbed);
        $this->assertSame(2, $meta['supervisors']);

        // Verify the pseudonyms can be resolved back in vault
        $mapping = $this->pseudonyms->getMappingForScope('scope_test_fuzzy');
        $this->assertContains('Majano Siliezar, Michael E', $mapping);
        $this->assertContains('Ramirez Pineda, Janeth C', $mapping);
    }

    public function test_extract_and_replace_entities_with_typos(): void
    {
        $text = 'haz un analisis del equipo de micheal';
        $meta = [];
        $scrubbed = $this->matcher->extractAndReplaceEntities($text, 'scope_test_typo', $this->pseudonyms, $meta);

        $this->assertStringNotContainsString('micheal', strtolower($scrubbed));
        $this->assertStringContainsString('SUP_', $scrubbed);
        $this->assertSame(1, $meta['supervisors']);

        $mapping = $this->pseudonyms->getMappingForScope('scope_test_typo');
        $this->assertContains('Majano Siliezar, Michael E', $mapping);
    }

    public function test_find_best_agent_match(): void
    {
        $match = $this->matcher->findBestAgentMatch('Fatima Aguilar');
        $this->assertSame('Aguilar Marroquin, Fatima A', $match);

        $matchBms = $this->matcher->findBestAgentMatch('Jose Amaya');
        $this->assertSame('Amaya Aguirre, Jose P', $matchBms);
    }
}
