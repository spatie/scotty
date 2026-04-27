<?php

namespace App\Commands;

use App\Updater\SelfUpdater;
use App\Updater\UpdateCachePath;
use App\Updater\UpdateChecker;
use LaravelZero\Framework\Commands\Command;
use Phar;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class SelfUpdateCommand extends Command
{
    protected $signature = 'self-update
        {--force : Re-download the latest release even if it matches the current version}';

    protected $description = 'Update Scotty to the latest GitHub release';

    public function handle(): int
    {
        $pharPath = Phar::running(false);

        if ($pharPath === '') {
            error('Self-update only works for the phar install. Use `composer global update spatie/scotty` instead.');

            return 1;
        }

        $currentVersion = $this->getApplication()->getVersion();

        $checker = new UpdateChecker(
            currentVersion: $currentVersion,
            cacheDirectory: UpdateCachePath::default(),
        );

        $newerVersion = $checker->findNewerVersion();

        if ($newerVersion === null) {
            if (! $this->option('force')) {
                info("You're already on the latest version ({$currentVersion}).");

                return 0;
            }
        }

        $targetVersion = $newerVersion ?? $currentVersion;

        info("Updating Scotty from {$currentVersion} to {$targetVersion}...");

        $result = (new SelfUpdater)->update(
            $targetVersion,
            $pharPath,
            beforeCommit: function () use ($targetVersion): void {
                info("Successfully updated to {$targetVersion}.");
            },
        );

        if ($result->succeeded) {
            return 0;
        }

        warning("Update failed: {$result->error}");

        return 1;
    }
}
