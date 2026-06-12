<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Fixtures;

use MetaFramework\Mediaclass\Models\Media;

class PostWithCustomMediaPath extends PostWithGroupSizes
{
    public string $mediaFolder = 'custom-key';

    public function mediaclassSettings(): array
    {
        return array_merge(parent::mediaclassSettings(), [
            'banner' => [
                'label' => 'Banner',
                'sizes' => [
                    'xl' => ['width' => 1920, 'height' => 1080],
                    'main' => ['width' => 1200, 'height' => 675],
                ],
            ],
        ]);
    }

    public function mediaclassFolderName(): string
    {
        return $this->mediaFolder;
    }

    public function mediaclassFileName(string $filename, string $extension, ?string $sizeKey = null, ?Media $media = null): string
    {
        $extension = trim($extension, '.');
        $extension = $extension !== '' ? '.' . $extension : '';

        if ($sizeKey && (!$media || $media->sizeable())) {
            return $filename . '_' . $sizeKey . $extension;
        }

        return $filename . $extension;
    }
}
