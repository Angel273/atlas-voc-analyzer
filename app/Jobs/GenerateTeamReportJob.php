<?php

namespace App\Jobs;

use App\Models\TeamReport;
use App\Services\Reports\AiTeamReportNarrativeService;
use App\Services\Reports\TeamPdfRenderer;
use App\Services\Reports\TeamReportDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateTeamReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    public function __construct(
        public TeamReport $teamReport
    ) {}

    public function handle(
        TeamReportDataService $dataService,
        AiTeamReportNarrativeService $narrativeService,
        TeamPdfRenderer $renderer
    ): void {
        $this->teamReport->update([
            'status' => 'processing',
            'progress' => 15,
            'stage' => 'Extrayendo métricas y verbatims del equipo...',
            'error_message' => null,
        ]);

        try {
            $team = $this->teamReport->team;
            $options = $this->teamReport->parameters ?? [];
            $fromDate = $this->teamReport->period_from->toDateString();
            $toDate = $this->teamReport->period_to->toDateString();

            // 1. Gather comprehensive report data
            $reportData = $dataService->buildReportData($team, $fromDate, $toDate, $options);

            // Update progress
            $this->teamReport->update([
                'progress' => 45,
                'stage' => 'Analizando voz del cliente y generando diagnóstico con IA...',
            ]);

            // 2. Generate AI narrative or deterministic fallback
            $narrativeResult = $narrativeService->generateNarrative($reportData, [
                'model' => $this->teamReport->model,
            ]);

            // Update progress
            $this->teamReport->update([
                'progress' => 75,
                'stage' => 'Maquetando y renderizando documento PDF...',
            ]);

            // 3. Render and store PDF
            $renderResult = $renderer->renderAndStore(
                $this->teamReport,
                $reportData,
                $narrativeResult['narrative']
            );

            // Update progress
            $this->teamReport->update([
                'progress' => 95,
                'stage' => 'Firmando reporte con hash criptográfico SHA-256...',
            ]);

            // 4. Update team report status to completed
            $this->teamReport->update([
                'status' => 'completed',
                'progress' => 100,
                'stage' => 'Completado con éxito',
                'data_version' => $reportData['data_version'],
                'metrics_data' => $reportData,
                'narrative' => $narrativeResult['narrative'],
                'tokens_used' => $narrativeResult['tokens_used'],
                'file_path' => $renderResult['file_path'],
                'file_hash' => $renderResult['file_hash'],
                'file_size' => $renderResult['file_size'],
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to generate team report #{$this->teamReport->id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            $this->teamReport->update([
                'status' => 'failed',
                'progress' => 0,
                'stage' => 'Error: '.$e->getMessage(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
