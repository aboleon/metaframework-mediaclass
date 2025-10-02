<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class MediaclassServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mediaclass.php', 'mfw-mediaclass');

        $this->app->singleton('mediaclass', fn() => new Mediaclass());
    }

    public function boot(): void
    {
        Blade::componentNamespace('MetaFramework\\Mediaclass\\Components', 'mediaclass');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mediaclass');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'mediaclass');
        $this->loadRoutesFrom(__DIR__.'/../routes/public.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/panel.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mediaclass.php' => config_path('mfw-mediaclass.php'),
            ], 'mfw-mediaclass-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/mediaclass'),
            ], 'mfw-mediaclass-views');

            $this->publishes([
                __DIR__.'/../resources/lang' => lang_path('vendor/mediaclass'),
            ], 'mfw-mediaclass-lang');

            $this->publishes([
                __DIR__.'/../public' => public_path('vendor/mfw/mediaclass'),
            ], 'mfw-mediaclass-assets');

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'mfw-mediaclass-migrations');
        }
    }
}
