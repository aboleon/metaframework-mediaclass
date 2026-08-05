<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\VideoEmbedders;

use MetaFramework\Mediaclass\Contracts\EmbedProvider;
use MetaFramework\Mediaclass\Data\EmbedAspectRatio;
use MetaFramework\Mediaclass\Data\ExternalVideoEmbed;

class VimeoEmbedProvider implements EmbedProvider
{
    private const VIMEO_HOSTS = ['vimeo.com', 'www.vimeo.com'];

    private const PLAYER_HOST = 'player.vimeo.com';

    private const VIDEO_PATH_PATTERN = '#^(?:channels/[^/]+/|groups/[^/]+/videos/|album/[0-9]+/video/|showcase/[0-9]+/video/|ondemand/[^/]+/)?([0-9]+)$#i';

    private const PLAYER_PATH_PATTERN = '#^video/([0-9]+)$#i';

    public function supports(string $url): bool
    {
        return $this->videoDetails($url) !== null;
    }

    public function embed(string $url): ?ExternalVideoEmbed
    {
        $videoDetails = $this->videoDetails($url);

        if ($videoDetails === null) {
            return null;
        }

        $src = 'https://player.vimeo.com/video/' . $videoDetails['id'];

        if ($videoDetails['hash'] !== null) {
            $src .= '?h=' . $videoDetails['hash'];
        }

        return new ExternalVideoEmbed(
            $src,
            [
                'allow' => 'autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share',
                'allowfullscreen' => true,
            ],
            new EmbedAspectRatio(16, 9),
        );
    }

    /**
     * @return array{id: string, hash: ?string}|null
     */
    private function videoDetails(string $url): ?array
    {
        $parts = parse_url($url);

        if (!$this->hasValidUrlStructure($parts)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $pattern = $host === self::PLAYER_HOST
            ? self::PLAYER_PATH_PATTERN
            : self::VIDEO_PATH_PATTERN;

        if (preg_match($pattern, $path, $matches) !== 1) {
            return null;
        }

        return [
            'id' => $matches[1],
            'hash' => $this->embedHash((string) ($parts['query'] ?? '')),
        ];
    }

    /**
     * @param  array<string, int|string>|false  $parts
     */
    private function hasValidUrlStructure(array|false $parts): bool
    {
        if (!is_array($parts)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && in_array($host, [...self::VIMEO_HOSTS, self::PLAYER_HOST], true)
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['port'])
            && !isset($parts['fragment']);
    }

    private function embedHash(string $queryString): ?string
    {
        parse_str($queryString, $query);
        $hash = $query['h'] ?? null;

        return is_string($hash) && preg_match('/^[A-Za-z0-9]+$/', $hash) === 1
            ? $hash
            : null;
    }
}
