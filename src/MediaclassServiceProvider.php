<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;

class MediaclassServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mfw-mediaclass.php', 'mfw-mediaclass');

        $this->app->singleton('mediaclass', fn() => new Mediaclass());
    }

    public function boot(): void
    {
        Blade::componentNamespace('MetaFramework\\Mediaclass\\Components', 'mediaclass');

        // Register simplified component with mfw- prefix
        Blade::component('mfw-media', \MetaFramework\Mediaclass\Components\Media::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mediaclass');
        $this->registerTranslations();
        $this->loadRoutesFrom(__DIR__.'/../routes/public.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/panel.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mfw-mediaclass.php' => config_path('mfw-mediaclass.php'),
            ], 'mfw-mediaclass-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/mediaclass'),
            ], 'mfw-mediaclass-views');

            $this->publishes([
                __DIR__.'/../resources/lang/en' => lang_path('en'),
                __DIR__.'/../resources/lang/fr' => lang_path('fr'),
            ], 'mfw-mediaclass-lang');

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'mfw-mediaclass-migrations');

            $this->publishes([
                __DIR__.'/../public/vendor/mfw/mediaclass' => public_path('vendor/mfw/mediaclass'),
            ], 'mfw-mediaclass-assets');
        }
    }

    protected function registerTranslations(): void
    {
        $langPath = __DIR__.'/../resources/lang';

        foreach (['en', 'fr'] as $locale) {
            $projectFile = lang_path("{$locale}/mfw-mediaclass.php");

            if (!file_exists($projectFile)) {
                $packageFile = "{$langPath}/{$locale}/mfw-mediaclass.php";
                if (file_exists($packageFile)) {
                    $lines = $this->flattenTranslations(require $packageFile, 'mfw-mediaclass');
                    Lang::addLines($lines, $locale, '*');
                }
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
