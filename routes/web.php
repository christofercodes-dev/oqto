<?php

use App\Http\Controllers\AgencyController;
use App\Http\Controllers\BokaDemoLogController;
use App\Http\Controllers\BokaDemoLookupController;
use App\Http\Controllers\EcosystemController;
use App\Http\Controllers\IntercomWebhookController;
use App\Http\Controllers\SupportController;
use Illuminate\Support\Facades\Route;

// POST (inte GET) eftersom e-postadressen inte ska hamna i URL:en och därmed
// i serverloggar. Rate-limitad eftersom uppslaget anropar Bolagsverket.
Route::post('/boka-demo/lookup', BokaDemoLookupController::class)
    ->middleware('throttle:30,1')
    ->name('boka-demo.lookup');

Route::post('/boka-demo/log', BokaDemoLogController::class)
    ->middleware('throttle:30,1')
    ->name('boka-demo.log');

// Intercom skickar webhooks hit; anropet verifieras med signaturen i
// controllern (inte CSRF), se bootstrap/app.php.
Route::post('/webhooks/intercom', IntercomWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.intercom');

// Hjälpcentret - artiklarna kommer från Intercom (se App\Services\Intercom\HelpCenter).
Route::get('/support', [SupportController::class, 'index'])->name('support.index');

// Statusen visas på /support. /support/status leder vidare dit, och check-
// endpointen ger JSON åt sidans skript. Måste ligga före {category}-rutterna nedan.
Route::get('/support/status', [SupportController::class, 'status'])->name('support.status');
Route::get('/support/status/check', [SupportController::class, 'statusCheck'])
    ->middleware('throttle:60,1')
    ->name('support.status.check');

Route::get('/support/{category}', [SupportController::class, 'category'])->name('support.category');
Route::get('/support/{category}/{article}', [SupportController::class, 'article'])->name('support.article');

// Byråernas sidor med egen logga och färger. Innehållet hämtas från funktionerna
// under /for-foretagare (se App\Services\Foretagare\FeatureCatalog).
Route::get('/byra/{agency}', [AgencyController::class, 'index'])->name('agency.index');
Route::get('/byra/{agency}/{feature}', [AgencyController::class, 'feature'])->name('agency.feature');

// Ekosystemen visar hur flera integrationer fungerar tillsammans. Posterna
// själva ligger i samlingen "ekosystem" (/integrationer/ekosystem/{slug}).
Route::get('/integrationer/ekosystem', [EcosystemController::class, 'index'])->name('ecosystem.index');
