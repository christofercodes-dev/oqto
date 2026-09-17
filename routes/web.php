<?php

use App\Http\Controllers\BokaDemoLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/boka-demo/lookup', BokaDemoLookupController::class)->name('boka-demo.lookup');