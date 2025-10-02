<?php

use MetaFramework\Mediaclass\Components\Printer;

function mediaclass_url(
    mixed  $model = null,
    string $size = 'sm',
): string
{
    return (new Printer(
        model: $model,
        size: $size,
        type: 'url'))->render();
}