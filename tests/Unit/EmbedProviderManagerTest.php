<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Unit;

use MetaFramework\Mediaclass\Contracts\EmbedProvider;
use MetaFramework\Mediaclass\Data\ExternalVideoEmbed;
use MetaFramework\Mediaclass\Support\EmbedProviderManager;
use MetaFramework\Mediaclass\Tests\TestCase;
use RuntimeException;

class EmbedProviderManagerTest extends TestCase
{
    public function test_it_renders_with_a_registered_provider(): void
    {
        $provider = new class implements EmbedProvider
        {
            public function supports(string $url): bool
            {
                return $url === 'https://videos.example.com/watch/123';
            }

            public function embed(string $url): ?ExternalVideoEmbed
            {
                return new ExternalVideoEmbed($url, ['title' => 'Example']);
            }
        };

        $embed = (new EmbedProviderManager)
            ->register($provider)
            ->embed('https://videos.example.com/watch/123');

        $this->assertSame('https://videos.example.com/watch/123', $embed?->src);
        $this->assertSame(['title' => 'Example'], $embed?->attributes);
    }

    public function test_it_continues_when_a_provider_fails(): void
    {
        $failingProvider = new class implements EmbedProvider
        {
            public function supports(string $url): bool
            {
                throw new RuntimeException('Provider unavailable');
            }

            public function embed(string $url): ?ExternalVideoEmbed
            {
                return null;
            }
        };

        $fallbackProvider = new class implements EmbedProvider
        {
            public function supports(string $url): bool
            {
                return true;
            }

            public function embed(string $url): ?ExternalVideoEmbed
            {
                return new ExternalVideoEmbed($url);
            }
        };

        $manager = new EmbedProviderManager(providers: [$failingProvider, $fallbackProvider]);

        $this->assertSame(
            'https://videos.example.com/watch/123',
            $manager->embed('https://videos.example.com/watch/123')?->src,
        );
    }
}
