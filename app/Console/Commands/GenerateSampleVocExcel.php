<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateSampleVocExcel extends Command
{
    protected $signature = 'atlas:sample-excel {path=sample_voc_data.xlsx}';
    protected $description = 'Generate a realistic sample VOC Excel workbook with diverse headers';

    public function handle(): int
    {
        $path = $this->argument('path');
        $spreadsheet = new Spreadsheet();

        // Sheet 1: Metadata + VOC Surveys
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('VOC Feedback Export');

        // Rows 1-3: Report Metadata
        $sheet->setCellValue('A1', 'REPORT: Voice of Customer Weekly Survey Summary');
        $sheet->setCellValue('A2', 'GENERATED: 2026-09-08 | SOURCE: CardWorks VOC System');
        $sheet->setCellValue('A3', 'STATUS: Production Data Extract');

        // Row 4: Custom Headers (different from internal Atlas attribute names to test mapping)
        $headers = [
            'Survey Identifier',
            'NPS',
            'CSAT %',
            'Professionalism',
            'Customer Comment',
            'Employee ID',
            'Team Leader',
            'Survey Date',
            'Wave Name',
            'Days in Production',
        ];

        $colLetter = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue("{$colLetter}4", $h);
            $colLetter++;
        }

        // Rows 5+: Realistic Data Rows
        $supervisors = ['Carlos Gomez', 'Elena Alvarez', 'Roberto Vega', 'Patricia Lima'];
        $agents = ['BMS-8491', 'BMS-1102', 'BMS-5532', 'BMS-9941', 'BMS-2210', 'BMS-7714'];
        $waves = ['Wave 12', 'Wave 14', 'Wave 15'];
        $comments = [
            'The representative took time to listen and helped me with my invoice payment. Very grateful!',
            'App was confusing, but agent explained how to navigate the account settings.',
            'Unexpected fee on my statement, still waiting for refund clarification.',
            'Great experience, friendly tone, solved the inquiry in under 3 minutes.',
            'Had to repeat myself twice, but resolution was satisfactory.',
            'Excellent customer care, very professional demeanor.',
            'The system gave me an error code, agent reset my credentials immediately.',
            'Everything was fine, standard service call.',
        ];

        $rowCount = 60;
        for ($i = 1; $i <= $rowCount; $i++) {
            $rowNum = $i + 4;
            $surveyId = sprintf('SRV-2026-%04d', $i);
            $nps = [-1, 0, 1, 0.8, -0.6, 1, 0.5, 1, 0.9, -0.2][$i % 10];
            $csat = [95, 80, 100, 70, 90, 85, 100, 60, 92, 88][$i % 10];
            $prof = [100, 90, 95, 85, 90, 95, 100, 80, 98, 92][$i % 10];
            $comment = $comments[$i % count($comments)];
            $bms = $agents[$i % count($agents)];
            $sup = $supervisors[$i % count($supervisors)];
            $date = date('Y-m-d', strtotime("2026-08-01 + {$i} days"));
            $wave = $waves[$i % count($waves)];
            $tenure = 30 + ($i * 7) % 300;

            $sheet->setCellValue("A{$rowNum}", $surveyId);
            $sheet->setCellValue("B{$rowNum}", $nps);
            $sheet->setCellValue("C{$rowNum}", $csat);
            $sheet->setCellValue("D{$rowNum}", $prof);
            $sheet->setCellValue("E{$rowNum}", $comment);
            $sheet->setCellValue("F{$rowNum}", $bms);
            $sheet->setCellValue("G{$rowNum}", $sup);
            $sheet->setCellValue("H{$rowNum}", $date);
            $sheet->setCellValue("I{$rowNum}", $wave);
            $sheet->setCellValue("J{$rowNum}", $tenure);
        }

        // Sheet 2: Secondary Empty Worksheet for testing multi-sheet picker
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Audit Log Metadata');
        $sheet2->setCellValue('A1', 'Audit log entries...');

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $this->info("Generated sample Excel at: {$path}");
        return Command::SUCCESS;
    }
}
