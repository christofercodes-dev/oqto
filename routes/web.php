<?php

use App\Http\Controllers\BokaDemoLogController;
use App\Http\Controllers\BokaDemoLookupController;
use Illuminate\Support\Facades\Route;

// POST (inte GET) eftersom e-postadressen inte ska hamna i URL:en och därmed
// i serverloggar. Rate-limitad eftersom uppslaget anropar Bolagsverket.
Route::post('/boka-demo/lookup', BokaDemoLookupController::class)
    ->middleware('throttle:30,1')
    ->name('boka-demo.lookup');

Route::post('/boka-demo/log', BokaDemoLogController::class)
    ->middleware('throttle:30,1')
    ->name('boka-demo.log');
