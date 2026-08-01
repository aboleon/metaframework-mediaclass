<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Data;

final readonly class ExternalVideoEmbed
{
    /**
     * @param  array<string, bool|float|int|string>  $attributes
     */
    public function __construct(
        public string $src,
        public array $attributes = [],
        public ?EmbedAspectRatio $aspectRatio = null,
    ) {}
}
