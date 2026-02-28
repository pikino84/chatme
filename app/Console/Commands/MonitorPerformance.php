<?php

namespace App\Console\Commands;

use App\Services\PerformanceMonitorService;
use Illuminate\Console\Command;

class MonitorPerformance extends Command
{
    protected $signature = 'monitor:performance';
    protected $description = 'Check performance thresholds and generate alerts';

    public function handle(PerformanceMonitorService $service): int
    {
        $results = $service->runAllChecks();

        $this->info('Queue backlog alert: ' . ($results['queue_backlog'] ? 'YES' : 'no'));
        $this->info('Failed jobs alert: ' . ($results['failed_jobs'] ? 'YES' : 'no'));
        $this->info('Usage limit alerts: ' . $results['usage_limits']);

        return Command::SUCCESS;
    }
}
