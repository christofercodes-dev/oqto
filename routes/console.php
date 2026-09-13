<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Den externa .NET-tjänsten dumpar en ny integrations.json (+ bilder)
// till S3-bucketen under natten - kör importen strax efter, kl 03:00.
// Kräver att serverns cron kör "php artisan schedule:run" varje minut
// (på Forge: sitans "Scheduler"-inställning måste vara påslagen).
Schedule::command('integrations:import')->dailyAt('03:00');
