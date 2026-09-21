<?php

namespace App\Services\Reports;

use App\Models\TeamReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class TeamPdfRenderer
{
    /**
     * Render the team report to PDF, save to storage, and compute SHA-256 hash.
     *
     * @param  array<string, mixed>  $reportData
     * @param  array<string, mixed>  $narrative
     * @return array{
     *     file_path: string,
     *     file_hash: string,
     *     file_size: int,
     *     content: string
     * }
     */
    public function renderAndStore(TeamReport $teamReport, array $reportData, array $narrative): array
    {
        $pdf = Pdf::loadView('reports.team-pdf', [
            'report' => $teamReport,
            'data' => $reportData,
            'narrative' => $narrative,
        ]);

        $pdf->setPaper('letter', 'portrait');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', false);

        $output = $pdf->output();
        $fileHash = hash('sha256', $output);
        $fileSize = strlen($output);

        $relativePath = "reports/{$teamReport->id}.pdf";
        Storage::disk('local')->put($relativePath, $output);

        $teamReport->update([
            'file_path' => $relativePath,
            'file_hash' => $fileHash,
            'file_size' => $fileSize,
        ]);

        return [
            'file_path' => $relativePath,
            'file_hash' => $fileHash,
            'file_size' => $fileSize,
            'content' => $output,
        ];
    }
}
