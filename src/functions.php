<?php

declare(strict_types=1);

use Illuminate\Support\HtmlString;
use MetaFramework\Mediaclass\Components\Printer;
use MetaFramework\Mediaclass\Facades\MediaclassFacade;
use MetaFramework\Mediaclass\Models\Media;

function mediaclass_url(
    mixed $model = null,
    string $size = 'sm',
): string {
    return (new Printer(
        model: $model,
        size: $size,
        type: 'url'))->output();
}

/**
 * @param  array<string, mixed>  $options
 */
function mediaclass_embed(Media|string|null $source, array $options = []): HtmlString
{
    return MediaclassFacade::embed($source, $options);
}
