<?php

use Illuminate\Support\Facades\Route;
use MetaFramework\Mediaclass\Http\Controllers\AjaxController;

Route::middleware(['web'])->post('mediaclass-ajax', [AjaxController::class, 'distribute'])->name('mediaclass.ajax');