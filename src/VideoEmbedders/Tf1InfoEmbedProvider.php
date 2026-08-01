<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\VideoEmbedders;

use MetaFramework\Mediaclass\Contracts\EmbedProvider;
use MetaFramework\Mediaclass\Data\ExternalVideoEmbed;

class Tf1InfoEmbedProvider implements EmbedProvider
{
    private const PLAYER_PATH_PATTERN = '#^/player/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/?$#i';

    public function supports(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === 'www.tf1info.fr'
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['port'])
            && !isset($parts['query'])
            && !isset($parts['fragment'])
            && preg_match(self::PLAYER_PATH_PATTERN, (string) ($parts['path'] ?? '')) === 1;
    }

    public function embed(string $url): ?ExternalVideoEmbed
    {
        if (!$this->supports($url)) {
            return null;
        }

        return new ExternalVideoEmbed($url, [
            'allow' => 'autoplay; encrypted-media; fullscreen; picture-in-picture',
            'allowfullscreen' => true,
        ]);
    }
}
