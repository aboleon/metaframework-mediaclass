<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass;

use Cohensive\OEmbed\Factory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;
use MetaFramework\Mediaclass\Console\UpdateMediaclassCommand;
use MetaFramework\Mediaclass\Support\EmbedProviderManager;
use MetaFramework\Mediaclass\VideoEmbedders\Tf1InfoEmbedProvider;

class MediaclassServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mfw-mediaclass.php', 'mfw-mediaclass');

        $this->app->singleton(
            EmbedProviderManager::class,
            fn (Container $app): EmbedProviderManager => new EmbedProviderManager($app),
        );
        $this->callAfterResolving(
            EmbedProviderManager::class,
            static function (EmbedProviderManager $manager): void {
                $manager->register(Tf1InfoEmbedProvider::class);
            },
        );
        $this->app->singleton(
            Mediaclass::class,
            fn (Container $app): Mediaclass => new Mediaclass(new Factory, $app->make(EmbedProviderManager::class)),
        );
        $this->app->alias(Mediaclass::class, 'mediaclass');
    }

    public function boot(): void
    {
        Blade::componentNamespace('MetaFramework\\Mediaclass\\Components', 'mediaclass');

        // Register simplified component with mfw- prefix
        Blade::component('mfw-media', Components\Media::class);

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'mediaclass');
        $this->registerTranslations();
        $this->loadRoutesFrom(__DIR__ . '/../routes/public.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/panel.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                UpdateMediaclassCommand::class,
            ]);

            $migrationTimestamp = date('Y_m_d_His');
            $modelKeysMigrationTimestamp = date('Y_m_d_His', time() + 1);
            $sortOrderMigrationTimestamp = date('Y_m_d_His', time() + 2);

            $this->publishes([
                __DIR__ . '/../config/mfw-mediaclass.php' => config_path('mfw-mediaclass.php'),
            ], 'mfw-mediaclass-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/mediaclass'),
            ], 'mfw-mediaclass-views');

            $this->publishes([
                __DIR__ . '/../resources/lang/en' => lang_path('en'),
                __DIR__ . '/../resources/lang/fr' => lang_path('fr'),
                __DIR__ . '/../resources/lang/bg' => lang_path('bg'),
            ], 'mfw-mediaclass-lang');

            $this->publishes([
                __DIR__ . '/../database/migrations/create_mediaclass.php' => database_path("migrations/{$migrationTimestamp}_create_mediaclass.php"),
                __DIR__ . '/../database/migrations/create_mediaclass_model_keys.php' => database_path("migrations/{$modelKeysMigrationTimestamp}_create_mediaclass_model_keys_table.php"),
                __DIR__ . '/../database/migrations/add_sort_order_to_mediaclass.php' => database_path("migrations/{$sortOrderMigrationTimestamp}_add_sort_order_to_mediaclass.php"),
            ], 'mfw-mediaclass-migrations');

            $this->publishes([
                __DIR__ . '/../database/migrations/create_mediaclass_model_keys.php' => database_path("migrations/{$modelKeysMigrationTimestamp}_create_mediaclass_model_keys_table.php"),
            ], 'mfw-mediaclass-model-keys-migration');

            $this->publishes([
                __DIR__ . '/../database/migrations/add_sort_order_to_mediaclass.php' => database_path("migrations/{$sortOrderMigrationTimestamp}_add_sort_order_to_mediaclass.php"),
            ], 'mfw-mediaclass-sort-order-migration');

            $this->publishes([
                __DIR__ . '/../public/vendor/mfw-mediaclass' => public_path('vendor/mfw-mediaclass'),
            ], 'mfw-mediaclass-assets');
        }
    }

    protected function registerTranslations(): void
    {
        $langPath = __DIR__ . '/../resources/lang';

        foreach (['en', 'fr', 'bg'] as $locale) {
            $packageFile = "{$langPath}/{$locale}/mfw-mediaclass.php";
            if (file_exists($packageFile)) {
                $lines = $this->flattenTranslations(require $packageFile, 'mfw-mediaclass');
                Lang::addLines($lines, $locale, '*');
            }

            $projectFile = lang_path("{$locale}/mfw-mediaclass.php");
            if (file_exists($projectFile)) {
                $lines = $this->flattenTranslations(require $projectFile, 'mfw-mediaclass');
                Lang::addLines($lines, $locale, '*');
            }
        }
    }

    protected function flattenTranslations(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenTranslations($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }
}
