<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DataImport\ImportExecutionService;
use Illuminate\Console\Command;

class ImportSampleVocExcel extends Command
{
    protected $signature = 'atlas:import-sample {path=sample_voc_data.xlsx}';
    protected $description = 'Execute end-to-end import of sample VOC data';

    public function handle(ImportExecutionService $executionService): int
    {
        $path = $this->argument('path');
        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return Command::FAILURE;
        }

        $admin = User::where('email', 'admin@atlas.local')->first();

        $mapping = [
            'survey_id' => 'A',
            'nps_score' => 'B',
            'csat_score' => 'C',
            'professionalism_score' => 'D',
            'verbatim' => 'E',
            'agent_bms' => 'F',
            'supervisor' => 'G',
            'survey_date' => 'H',
            'wave' => 'I',
            'tenure_days' => 'J',
        ];

        $transformations = [
            'csat_score' => ['percentage' => true],
            'professionalism_score' => ['percentage' => true],
        ];

        $import = $executionService->executeImport(
            filePath: $path,
            originalFilename: 'sample_voc_data.xlsx',
            sheetName: 'VOC Feedback Export',
            headerRow: 4,
            columnMapping: $mapping,
            transformations: $transformations,
            user: $admin
        );

        $this->info("Import completed successfully!");
        $this->info("Total rows: {$import->row_count} | Accepted: {$import->accepted_rows} | Duplicates: {$import->duplicate_rows} | Rejected: {$import->rejected_rows}");

        return Command::SUCCESS;
    }
}
