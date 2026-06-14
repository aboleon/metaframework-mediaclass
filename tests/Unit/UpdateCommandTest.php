<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Unit;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use LogicException;
use MetaFramework\Mediaclass\Console\UpdateMediaclassCommand;
use MetaFramework\Mediaclass\Tests\TestCase;
use Mockery;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class UpdateCommandTest extends TestCase
{
    public function test_update_command_declares_expected_options(): void
    {
        $command = new UpdateMediaclassCommand;
        $definition = $command->getDefinition();

        $this->assertSame('mediaclass:update', $command->getName());
        $this->assertTrue($definition->hasOption('force'));
        $this->assertTrue($definition->hasOption('config'));
        $this->assertTrue($definition->hasOption('migrations'));
        $this->assertTrue($definition->hasOption('migrate'));
        $this->assertTrue($definition->hasOption('views'));
    }

    public function test_update_command_does_not_publish_application_config_by_default(): void
    {
        $command = Mockery::mock(UpdateMediaclassCommand::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $command->shouldReceive('option')->with('force')->andReturn(true);
        $command->shouldReceive('option')->with('config')->andReturn(false);
        $command->shouldReceive('option')->with('views')->andReturn(false);
        $command->shouldReceive('option')->with('migrations')->andReturn(false);
        $command->shouldReceive('option')->with('migrate')->andReturn(false);
        $command->shouldReceive('publishAssets')->once()->with(true)->andReturn(SymfonyCommand::SUCCESS);
        $command->shouldReceive('publishTag')->once()->with('mfw-mediaclass-lang', true)->andReturn(SymfonyCommand::SUCCESS);
        $command->shouldNotReceive('publishTag')->with('mfw-mediaclass-config', Mockery::any());
        $command->shouldReceive('info')->once();

        $this->assertSame(SymfonyCommand::SUCCESS, $command->handle());
    }

    public function test_update_command_publishes_config_only_when_explicitly_requested(): void
    {
        $command = Mockery::mock(UpdateMediaclassCommand::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $command->shouldReceive('option')->with('force')->andReturn(false);
        $command->shouldReceive('option')->with('config')->andReturn(true);
        $command->shouldReceive('option')->with('views')->andReturn(false);
        $command->shouldReceive('option')->with('migrations')->andReturn(false);
        $command->shouldReceive('option')->with('migrate')->andReturn(false);
        $command->shouldReceive('publishAssets')->once()->with(false)->andReturn(SymfonyCommand::SUCCESS);
        $command->shouldReceive('publishTag')->once()->with('mfw-mediaclass-lang', false)->andReturn(SymfonyCommand::SUCCESS);
        $command->shouldReceive('publishTag')->once()->with('mfw-mediaclass-config', false)->andReturn(SymfonyCommand::SUCCESS);
        $command->shouldReceive('info')->once();

        $this->assertSame(SymfonyCommand::SUCCESS, $command->handle());
    }

    public function test_update_command_is_registered_with_artisan(): void
    {
        $this->artisan('list')->assertExitCode(0);

        $commands = $this->app->make(Kernel::class)->all();

        $this->assertArrayHasKey('mediaclass:update', $commands);
    }

    public function test_asset_cleanup_removes_only_the_published_destination(): void
    {
        $root = sys_get_temp_dir() . '/mediaclass-update-' . bin2hex(random_bytes(8));
        $source = $root . '/package/public/vendor/mfw-mediaclass';
        $destination = $root . '/application/public/vendor/mfw-mediaclass';
        $files = new Filesystem;

        $files->ensureDirectoryExists($source);
        $files->ensureDirectoryExists($destination);
        $files->put($source . '/mediaclass-uploader.js', 'source');
        $files->put($destination . '/uploader.js', 'stale');

        $command = new class extends UpdateMediaclassCommand
        {
            public function cleanAssets(string $source, string $destination): void
            {
                $this->cleanPublishedAssets($source, $destination);
            }
        };

        try {
            $command->cleanAssets($source, $destination);

            $this->assertDirectoryExists($source);
            $this->assertFileExists($source . '/mediaclass-uploader.js');
            $this->assertDirectoryDoesNotExist($destination);
        } finally {
            $files->deleteDirectory($root);
        }
    }

    public function test_asset_cleanup_refuses_to_delete_its_source_directory(): void
    {
        $root = sys_get_temp_dir() . '/mediaclass-update-' . bin2hex(random_bytes(8));
        $source = $root . '/public/vendor/mfw-mediaclass';
        $files = new Filesystem;
        $files->ensureDirectoryExists($source);
        $files->put($source . '/mediaclass-uploader.js', 'source');

        $command = new class extends UpdateMediaclassCommand
        {
            public function cleanAssets(string $source, string $destination): void
            {
                $this->cleanPublishedAssets($source, $destination);
            }
        };

        try {
            $this->expectException(LogicException::class);
            $command->cleanAssets($source, $source);
        } finally {
            $this->assertFileExists($source . '/mediaclass-uploader.js');
            $files->deleteDirectory($root);
        }
    }

    public function test_asset_cleanup_refuses_a_destination_nested_inside_its_source(): void
    {
        $root = sys_get_temp_dir() . '/mediaclass-update-' . bin2hex(random_bytes(8));
        $source = $root . '/public/vendor/mfw-mediaclass';
        $destination = $source . '/vendor/mfw-mediaclass';
        $files = new Filesystem;
        $files->ensureDirectoryExists($destination);
        $files->put($source . '/mediaclass-uploader.js', 'source');

        $command = new class extends UpdateMediaclassCommand
        {
            public function cleanAssets(string $source, string $destination): void
            {
                $this->cleanPublishedAssets($source, $destination);
            }
        };

        try {
            $this->expectException(LogicException::class);
            $command->cleanAssets($source, $destination);
        } finally {
            $this->assertFileExists($source . '/mediaclass-uploader.js');
            $files->deleteDirectory($root);
        }
    }
}
