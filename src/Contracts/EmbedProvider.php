<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Contracts;

use MetaFramework\Mediaclass\Data\ExternalVideoEmbed;

interface EmbedProvider
{
    public function supports(string $url): bool;

    public function embed(string $url): ?ExternalVideoEmbed;
}
