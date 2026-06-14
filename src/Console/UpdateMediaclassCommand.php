<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class UpdateMediaclassCommand extends Command
{
    protected $signature = 'mediaclass:update
        {--force : Overwrite already published files and force migrations in production}
        {--migrations : Publish Mediaclass migrations}
        {--migrate : Publish Mediaclass migrations and run the application migrations}
        {--views : Publish Mediaclass views for application customization}';

    protected $description = 'Publish Mediaclass package resources after installing or updating the package.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        foreach (['config', 'assets', 'lang'] as $resource) {
            $exitCode = $this->publishTag("mfw-mediaclass-{$resource}", $force);

            if ($exitCode !== SymfonyCommand::SUCCESS) {
                return $exitCode;
            }
        }

        if ((bool) $this->option('views')) {
            $exitCode = $this->publishTag('mfw-mediaclass-views', $force);

            if ($exitCode !== SymfonyCommand::SUCCESS) {
                return $exitCode;
            }
        }

        if ((bool) $this->option('migrations') || (bool) $this->option('migrate')) {
            $exitCode = $this->publishTag('mfw-mediaclass-migrations', $force);

            if ($exitCode !== SymfonyCommand::SUCCESS) {
                return $exitCode;
            }
        }

        if ((bool) $this->option('migrate')) {
            $this->line('Running application migrations...');

            $exitCode = $this->call('migrate', [
                '--force' => $force,
            ]);

            if ($exitCode !== SymfonyCommand::SUCCESS) {
                return $exitCode;
            }
        }

        $this->info('Mediaclass resources are up to date.');

        return SymfonyCommand::SUCCESS;
    }

    private function publishTag(string $tag, bool $force): int
    {
        $this->line("Publishing {$tag}...");

        return $this->call('vendor:publish', [
            '--tag' => $tag,
            '--force' => $force,
        ]);
    }
}
