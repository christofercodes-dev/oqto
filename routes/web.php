<?php

use App\Http\Controllers\BokaDemoLogController;
use App\Http\Controllers\BokaDemoLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/boka-demo/lookup', BokaDemoLookupController::class)->name('boka-demo.lookup');

Route::post('/boka-demo/log', BokaDemoLogController::class)
    ->middleware('throttle:30,1')
    ->name('boka-demo.log');
