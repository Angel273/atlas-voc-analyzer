<?php

namespace App\Console\Commands;

use App\Services\Organization\WorkforceBackfillService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('workforce:backfill')]
#[Description('Run idempotent backfill of workforce members, teams, memberships, and survey links.')]
class WorkforceBackfillCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(WorkforceBackfillService $backfillService): int
    {
        $this->info('Starting workforce backfill...');
        $result = $backfillService->run();

        $this->table(['Metric', 'Count'], [
            ['Agents Processed', $result['agents_processed']],
            ['Supervisors Processed', $result['supervisors_processed']],
            ['Teams Created', $result['teams_created']],
            ['Memberships Created', $result['memberships_created']],
            ['Surveys Linked', $result['surveys_linked']],
            ['Conflicts Detected', count($result['conflicts'])],
        ]);

        if (! empty($result['conflicts'])) {
            $this->warn('Identity / Supervisor conflicts detected:');
            foreach ($result['conflicts'] as $c) {
                $this->line(" - [{$c['type']}] {$c['description']}");
            }
        }

        $this->info('Workforce backfill completed successfully.');

        return Command::SUCCESS;
    }
}
