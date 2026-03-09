<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeployApp extends Command
{
    protected $signature = 'app:deploy {--seed : Run seeders after migration}';

    protected $description = 'Run all deployment tasks (migrate, cache, optimize)';

    public function handle(): int
    {
        $this->info('Starting deployment...');

        // 1. Run migrations
        $this->call('migrate', ['--force' => true]);

        // 2. Seed roles/permissions and plans (idempotent)
        if ($this->option('seed')) {
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\PlansAndFeaturesSeeder', '--force' => true]);
        }

        // 3. Cache configuration, routes, and views
        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('view:cache');
        $this->call('event:cache');

        // 4. Restart queue workers to pick up new code
        $this->call('queue:restart');

        // 5. Verify critical configuration
        $this->verifyConfiguration();

        $this->info('Deployment complete.');

        return Command::SUCCESS;
    }

    private function verifyConfiguration(): void
    {
        $warnings = [];

        if (config('app.debug') === true && app()->environment('production')) {
            $warnings[] = 'APP_DEBUG is true in production';
        }

        if (config('app.key') === null) {
            $warnings[] = 'APP_KEY is not set';
        }

        if (config('database.default') === 'sqlite' && app()->environment('production')) {
            $warnings[] = 'Using SQLite in production';
        }

        if (config('queue.default') === 'sync') {
            $warnings[] = 'Queue driver is sync - jobs will not be queued';
        }

        if (config('mail.default') === 'log' && app()->environment('production')) {
            $warnings[] = 'Mail driver is log - emails will not be sent';
        }

        if (empty($warnings)) {
            $this->info('Configuration checks passed.');
        } else {
            foreach ($warnings as $warning) {
                $this->warn("WARNING: {$warning}");
            }
        }
    }
}
