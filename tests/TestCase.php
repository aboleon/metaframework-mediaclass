<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use MetaFramework\Inputable\InputableServiceProvider;
use MetaFramework\Mediaclass\MediaclassServiceProvider;
use MetaFramework\Support\SupportServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            InputableServiceProvider::class,
            MediaclassServiceProvider::class,
            SupportServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('filesystems.default', 'testing');
        $app['config']->set('filesystems.disks.testing', [
            'driver' => 'local',
            'root' => sys_get_temp_dir() . '/mediaclass-tests',
            'url' => 'http://localhost/storage',
            'visibility' => 'public',
        ]);

        $app['config']->set('mfw-mediaclass', [
            'disk' => 'testing',
            'dimensions' => [
                'xl' => ['width' => 1920, 'height' => 1080],
                'lg' => ['width' => 1400, 'height' => 788],
                'md' => ['width' => 700, 'height' => 394],
                'sm' => ['width' => 400, 'height' => 225],
            ],
        ]);
    }

    protected function setUpDatabase(): void
    {
        Schema::create('mediaclass', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('group')->default('media');
            $table->string('subgroup')->nullable();
            $table->json('description')->nullable();
            $table->string('position')->default('left');
            $table->string('mime');
            $table->string('original_filename');
            $table->string('filename');
            $table->string('temp')->nullable();
            $table->json('storable')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
        });

        Schema::create('mediaclass_model_keys', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('access_key')->unique();
            $table->timestamps();

            $table->unique(['model_type', 'model_id']);
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $path = sys_get_temp_dir() . '/mediaclass-tests';
        if (is_dir($path)) {
            $this->deleteDirectory($path);
        }

        parent::tearDown();
    }

    protected function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
