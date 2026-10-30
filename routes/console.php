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

// Statamic cachar listan över filer i bucketen för alltid. Importen bygger om
// den, men bilder kan komma till i bucketen efter importen (eller före en
// release som har den ombyggnaden), och då skulle loggan saknas tills nästa
// deploy. Därför byggs listan om en gång till kl 06:00, efter nattens inläsning.
Schedule::command('integrations:refresh-assets')->dailyAt('06:00')->withoutOverlapping();

// Håller hjälpcentret (/support) färskt även om en webhook skulle missas.
Schedule::command('intercom:sync-help-center')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->when(fn () => filled(config('services.intercom.token')));
