<?php

namespace App\Jobs;

use App\Services\DripService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDripStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(DripService $dripService): void
    {
        $enrollments = $dripService->getPendingEnrollments();

        foreach ($enrollments as $enrollment) {
            try {
                $dripService->processNextStep($enrollment);
            } catch (\Throwable $e) {
                Log::error('Drip step processing failed', [
                    'enrollment_id' => $enrollment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
