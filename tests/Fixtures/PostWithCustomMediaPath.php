<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Fixtures;

use MetaFramework\Mediaclass\Models\Media;

class PostWithCustomMediaPath extends PostWithGroupSizes
{
    public function mediaclassFolderName(): string
    {
        return 'custom-key';
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
