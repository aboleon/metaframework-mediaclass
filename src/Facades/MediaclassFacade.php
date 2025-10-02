<?php

namespace  MetaFramework\Mediaclass\Facades;

use Illuminate\Support\Facades\Facade;

class MediaclassFacade extends Facade
{
    protected static $cached = false;

    public static function getFacadeAccessor(): string
    {
        return 'mediaclass';
    }
}
