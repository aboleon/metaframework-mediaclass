<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use LogicException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class UpdateMediaclassCommand extends Command
{
    protected $signature = 'mediaclass:update
        {--force : Overwrite already published files and force migrations in production}
        {--config : Publish the package config; combine with --force only to intentionally replace it}
        {--lang : Publish language files for application overrides}
        {--migrations : Publish Mediaclass migrations}
        {--migrate : Publish Mediaclass migrations and run the application migrations}
        {--views : Publish Mediaclass views for application customization}';

    protected $description = 'Publish Mediaclass package resources after installing or updating the package.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $exitCode = $this->publishAssets($force);

        if ($exitCode !== SymfonyCommand::SUCCESS) {
            return $exitCode;
        }

        if ((bool) $this->option('lang')) {
            $exitCode = $this->publishTag('mfw-mediaclass-lang', $force);

            if ($exitCode !== SymfonyCommand::SUCCESS) {
                return $exitCode;
            }
        }

        if ((bool) $this->option('config')) {
            $exitCode = $this->publishTag('mfw-mediaclass-config', $force);

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

    protected function publishAssets(bool $force): int
    {
        $source = dirname(__DIR__, 2) . '/public/vendor/mfw-mediaclass';
        $destination = public_path('vendor/mfw-mediaclass');

        try {
            $this->cleanPublishedAssets($source, $destination);
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return SymfonyCommand::FAILURE;
        }

        return $this->publishTag('mfw-mediaclass-assets', $force);
    }

    protected function cleanPublishedAssets(string $source, string $destination): void
    {
        $sourcePath = $this->normalizePath($source);
        $destinationPath = $this->normalizePath($destination);
        $files = new Filesystem;

        if (!$files->isDirectory($source)) {
            throw new LogicException("Mediaclass asset source [{$source}] does not exist.");
        }

        if (
            $sourcePath === $destinationPath
            || str_starts_with($sourcePath, $destinationPath . '/')
            || str_starts_with($destinationPath, $sourcePath . '/')
        ) {
            throw new LogicException(
                'Refusing to replace Mediaclass assets because the package source and publish destination overlap.',
            );
        }

        if ($files->isDirectory($destination) && !$files->deleteDirectory($destination)) {
            throw new LogicException("Unable to remove stale Mediaclass assets from [{$destination}].");
        }
    }

    protected function normalizePath(string $path): string
    {
        $resolvedPath = realpath($path) ?: $path;

        return mb_strtolower(rtrim(str_replace('\\', '/', $resolvedPath), '/'));
    }

    protected function publishTag(string $tag, bool $force): int
    {
        $this->line("Publishing {$tag}...");

        return $this->call('vendor:publish', [
            '--tag' => $tag,
            '--force' => $force,
        ]);
    }
}
