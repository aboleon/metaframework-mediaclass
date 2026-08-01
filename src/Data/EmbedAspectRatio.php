<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Data;

use InvalidArgumentException;

final readonly class EmbedAspectRatio
{
    public function __construct(
        public int $width,
        public int $height,
    ) {
        if ($this->width < 1 || $this->height < 1) {
            throw new InvalidArgumentException('Embed aspect ratio dimensions must be positive integers.');
        }
    }

    public function toCssValue(): string
    {
        return $this->width . ' / ' . $this->height;
    }
}
