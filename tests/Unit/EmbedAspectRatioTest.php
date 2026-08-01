<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Tests\Unit;

use InvalidArgumentException;
use MetaFramework\Mediaclass\Data\EmbedAspectRatio;
use MetaFramework\Mediaclass\Tests\TestCase;

class EmbedAspectRatioTest extends TestCase
{
    public function test_it_formats_a_valid_aspect_ratio(): void
    {
        $this->assertSame('16 / 9', (new EmbedAspectRatio(16, 9))->toCssValue());
    }

    public function test_it_rejects_non_positive_dimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EmbedAspectRatio(16, 0);
    }
}
