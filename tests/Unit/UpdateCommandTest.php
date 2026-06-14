<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Unit;

use Illuminate\Contracts\Console\Kernel;
use MetaFramework\Mediaclass\Console\UpdateMediaclassCommand;
use MetaFramework\Mediaclass\Tests\TestCase;

class UpdateCommandTest extends TestCase
{
    public function test_update_command_declares_expected_options(): void
    {
        $command = new UpdateMediaclassCommand;
        $definition = $command->getDefinition();

        $this->assertSame('mediaclass:update', $command->getName());
        $this->assertTrue($definition->hasOption('force'));
        $this->assertTrue($definition->hasOption('migrations'));
        $this->assertTrue($definition->hasOption('migrate'));
        $this->assertTrue($definition->hasOption('views'));
    }

    public function test_update_command_is_registered_with_artisan(): void
    {
        $this->artisan('list')->assertExitCode(0);

        $commands = $this->app->make(Kernel::class)->all();

        $this->assertArrayHasKey('mediaclass:update', $commands);
    }
}
