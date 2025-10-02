<?php

use Illuminate\Support\Facades\Route;
use MetaFramework\Mediaclass\Cropable;
use MetaFramework\Mediaclass\Models\Media;


Route::prefix('mediaclass')
    ->name('mediaclass.')
    ->middleware(['web', 'auth:sanctum'])
    ->group(function () {
        Route::get('cropable/{media}', fn(Media $media) => Cropable::form($media))->name('cropable');
    });
