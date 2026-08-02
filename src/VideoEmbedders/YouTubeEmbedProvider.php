<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\VideoEmbedders;

use MetaFramework\Mediaclass\Contracts\EmbedProvider;
use MetaFramework\Mediaclass\Data\EmbedAspectRatio;
use MetaFramework\Mediaclass\Data\ExternalVideoEmbed;

class YouTubeEmbedProvider implements EmbedProvider
{
    private const YOUTUBE_HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com'];

    private const SHORT_HOSTS = ['youtu.be', 'www.youtu.be'];

    public function supports(string $url): bool
    {
        return $this->videoId($url) !== null;
    }

    public function embed(string $url): ?ExternalVideoEmbed
    {
        $videoId = $this->videoId($url);

        if ($videoId === null) {
            return null;
        }

        return new ExternalVideoEmbed(
            'https://www.youtube.com/embed/' . $videoId,
            [
                'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
                'allowfullscreen' => true,
                'referrerpolicy' => 'strict-origin-when-cross-origin',
            ],
            new EmbedAspectRatio(16, 9),
        );
    }

    private function videoId(string $url): ?string
    {
        $parts = parse_url($url);

        if (!$this->hasValidUrlStructure($parts)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $videoId = in_array($host, self::SHORT_HOSTS, true)
            ? explode('/', $path)[0] ?? null
            : $this->youtubeVideoId($path, (string) ($parts['query'] ?? ''));

        return is_string($videoId) && preg_match('/^[A-Za-z0-9_-]+$/', $videoId) === 1
            ? $videoId
            : null;
    }

    /** @param  array<string, int|string>|false  $parts */
    private function hasValidUrlStructure(array|false $parts): bool
    {
        if (!is_array($parts)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && in_array($host, [...self::YOUTUBE_HOSTS, ...self::SHORT_HOSTS], true)
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['port'])
            && !isset($parts['fragment']);
    }

    private function youtubeVideoId(string $path, string $queryString): ?string
    {
        if ($path === 'watch') {
            parse_str($queryString, $query);

            return is_string($query['v'] ?? null) ? $query['v'] : null;
        }

        return preg_match('#^(?:embed|shorts|live)/([^/]+)$#', $path, $matches) === 1
            ? $matches[1]
            : null;
    }
}
