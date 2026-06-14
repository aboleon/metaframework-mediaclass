<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Unit;

use Illuminate\Contracts\Console\Kernel;
use Mockery;
use MetaFramework\Mediaclass\Console\UpdateMediaclassCommand;
use MetaFramework\Mediaclass\Tests\TestCase;
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
        $command->shouldReceive('publishTag')->once()->with('mfw-mediaclass-assets', true)->andReturn(SymfonyCommand::SUCCESS);
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
        $command->shouldReceive('publishTag')->once()->with('mfw-mediaclass-assets', false)->andReturn(SymfonyCommand::SUCCESS);
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
}
