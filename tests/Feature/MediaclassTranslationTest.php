<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use MetaFramework\Mediaclass\MediaclassServiceProvider;
use MetaFramework\Mediaclass\Tests\TestCase;

class MediaclassTranslationTest extends TestCase
{
    public function test_package_translations_are_loaded_from_the_package_namespace(): void
    {
        app()->setLocale('fr');

        $this->assertSame(
            'Enregistrer les réglages média',
            __('mfw-mediaclass::messages.buttons.save_media_settings'),
        );
    }

    public function test_laravel_vendor_translation_overrides_replace_only_customized_lines(): void
    {
        $filesystem = new Filesystem;
        $langPath = sys_get_temp_dir() . '/mediaclass-lang-' . bin2hex(random_bytes(8));
        $overridePath = $langPath . '/vendor/mfw-mediaclass/fr/messages.php';

        $filesystem->ensureDirectoryExists(dirname($overridePath));
        $filesystem->put($overridePath, <<<'PHP'
<?php

return [
    'buttons' => [
        'save_media_settings' => 'Réglages personnalisés',
    ],
];
PHP);

        /** @var FileLoader $loader */
        $loader = app('translation.loader');
        $loader->addPath($langPath);
        app()->setLocale('fr');

        try {
            $this->assertSame(
                'Réglages personnalisés',
                __('mfw-mediaclass::messages.buttons.save_media_settings'),
            );
            $this->assertSame('Annuler', __('mfw-mediaclass::messages.buttons.cancel'));
        } finally {
            $filesystem->deleteDirectory($langPath);
        }
    }

    public function test_language_files_publish_to_laravels_vendor_override_directory(): void
    {
        $paths = MediaclassServiceProvider::pathsToPublish(
            MediaclassServiceProvider::class,
            'mfw-mediaclass-lang',
        );

        $this->assertCount(1, $paths);
        $this->assertSame(
            realpath(dirname(__DIR__, 2) . '/resources/lang'),
            realpath((string) array_key_first($paths)),
        );
        $this->assertSame(lang_path('vendor/mfw-mediaclass'), reset($paths));
    }
}
