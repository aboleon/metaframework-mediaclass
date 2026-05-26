<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use MetaFramework\Mediaclass\Concerns\Mediaclass;
use MetaFramework\Mediaclass\Contracts\MediaclassInterface;
use MetaFramework\Mediaclass\Support\BridgeMedia;

class PostWithBridgeMedia extends Model implements MediaclassInterface
{
    use Mediaclass;

    public static array $syncedBridgeMedia = [];

    public static array $deletedBridgeMedia = [];

    protected $guarded = [];

    protected $table = 'posts';

    public static function resetBridgeState(): void
    {
        self::$syncedBridgeMedia = [];
        self::$deletedBridgeMedia = [];
    }

    public function mediaclassBridgeMedia(string $group, ?string $subgroup = null): array
    {
        if ($group !== 'cover' || $subgroup !== null) {
            return [];
        }

        return [
            new BridgeMedia(
                id: 'legacy:1',
                group: 'cover',
                mime: 'image/jpeg',
                original_filename: 'legacy.jpg',
                filename: 'legacy.jpg',
                urls: [
                    'sm' => 'https://example.test/legacy-sm.jpg',
                    'xl' => 'https://example.test/legacy-xl.jpg',
                ],
                description: ['en' => 'Legacy image'],
            ),
        ];
    }

    public function syncMediaclassBridgeMedia(array $bridgeMedia): void
    {
        self::$syncedBridgeMedia = $bridgeMedia;
    }

    public function deleteMediaclassBridgeMedia(string $bridgeId, string $group, ?string $subgroup = null): bool
    {
        self::$deletedBridgeMedia[] = compact('bridgeId', 'group', 'subgroup');

        return $bridgeId === 'legacy:1' && $group === 'cover' && $subgroup === null;
    }
}
