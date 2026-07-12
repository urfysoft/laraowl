<?php

namespace App\Console\Commands;

use App\Services\UpdateService;
use App\Support\Release;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class UpdateLaraOwl extends Command
{
    protected $signature = 'laraowl:update
                            {--check : Report whether an update is available without installing it}
                            {--dry-run : Print the steps that would run without executing them}
                            {--force : Skip the confirmation prompt and the working tree check}';

    protected $description = 'Check for and install the latest LaraOwl release';

    /**
     * How long any single update step may run before it is killed.
     */
    protected const STEP_TIMEOUT = 600;

    public function handle(UpdateService $updates): int
    {
        if (! $updates->enabled()) {
            $this->error('Update checks are disabled. Set LARAOWL_UPDATE_CHECK=true to enable them.');

            return self::FAILURE;
        }

        $current = $updates->currentVersion();
        $release = $updates->refresh();

        if (! $release) {
            $this->error("Could not reach the GitHub releases API for {$updates->repository()}.");

            return self::FAILURE;
        }

        if (! $release->isNewerThanCurrent()) {
            $this->info("LaraOwl is up to date (v{$current}).");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("LaraOwl v{$release->version} is available. You are running v{$current}.");
        $this->line($release->url);
        $this->newLine();

        if ($this->option('check')) {
            $this->comment('Run `php artisan laraowl:update` to install it.');

            return self::SUCCESS;
        }

        if (! $this->option('dry-run') && ! $this->passesPreflight()) {
            return self::FAILURE;
        }

        return $this->install($release);
    }

    /**
     * Verify the instance is in a state where it can update itself.
     */
    protected function passesPreflight(): bool
    {
        if (! is_dir(base_path('.git'))) {
            $this->error('This is not a git checkout, so it cannot be updated automatically.');
            $this->line('Download the release manually, then run `php artisan migrate --force`.');

            return false;
        }

        if (! $this->option('force') && $this->hasUncommittedChanges()) {
            $this->error('The working tree has uncommitted changes. Commit or stash them first.');
            $this->line('Pass --force to update anyway.');

            return false;
        }

        $this->warn('Back up your database before continuing. This will run migrations.');

        return $this->option('force') || $this->confirm('Install this update now?', false);
    }

    /**
     * Determine whether the git working tree is dirty.
     */
    protected function hasUncommittedChanges(): bool
    {
        $result = Process::path(base_path())
            ->run([$this->binary('git'), 'status', '--porcelain']);

        return $result->successful() && trim($result->output()) !== '';
    }

    /**
     * Run each update step, keeping the app in maintenance mode throughout.
     */
    protected function install(Release $release): int
    {
        $steps = $this->steps();

        if ($this->option('dry-run')) {
            $this->comment('Dry run — these steps would run in order:');
            foreach ($steps as $label => $command) {
                $this->line('  '.$label.': '.implode(' ', $command));
            }

            return self::SUCCESS;
        }

        $this->call('down');

        try {
            foreach ($steps as $label => $command) {
                $this->newLine();
                $this->info("→ {$label}");

                $result = Process::path(base_path())
                    ->timeout(self::STEP_TIMEOUT)
                    ->run($command, fn (string $type, string $output) => $this->output->write($output));

                if ($result->failed()) {
                    $this->newLine();
                    $this->error("Step failed: {$label}");
                    $this->line('The instance was left on the previous version. Resolve the error and retry.');

                    return self::FAILURE;
                }
            }
        } finally {
            $this->call('up');
        }

        $this->newLine();
        $this->info("LaraOwl has been updated to v{$release->version}.");

        return self::SUCCESS;
    }

    /**
     * The ordered update steps, keyed by the label shown to the operator.
     *
     * @return array<string, list<string>>
     */
    protected function steps(): array
    {
        $php = (string) (PHP_BINARY ?: 'php');
        $artisan = base_path('artisan');

        return [
            'Pulling the latest code' => [$this->binary('git'), 'pull', '--ff-only'],
            'Installing PHP dependencies' => [$this->binary('composer'), 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'],
            'Installing JS dependencies' => [$this->binary('npm'), 'ci'],
            'Building assets' => [$this->binary('npm'), 'run', 'build'],
            'Running migrations' => [$php, $artisan, 'migrate', '--force'],
            'Backfilling dashboard rollups' => [$php, $artisan, 'laraowl:rollups:backfill', '--missing', '--no-interaction'],
            'Clearing caches' => [$php, $artisan, 'optimize:clear'],
            'Restarting queue workers' => [$php, $artisan, 'queue:restart'],
        ];
    }

    /**
     * Resolve a configured executable name.
     */
    protected function binary(string $name): string
    {
        return (string) config("laraowl.binaries.{$name}");
    }
}
