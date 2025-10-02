<?php

namespace MetaFramework\Mediaclass;


use Illuminate\Support\Facades\Route;
use MetaFramework\Mediaclass\Models\Media;

class Routes
{
    public static function panel(): void
    {
        Route::prefix('mediaclass')->name('mediaclass.')->group(function() {
           Route::get('cropable/{media}', fn(Media $media) => Cropable::form($media))->name('cropable');
        });
    }
}
