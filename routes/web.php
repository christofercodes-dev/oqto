<?php

use Illuminate\Support\Facades\Route;
use Statamic\Facades\Entry;

Route::get('/jobs/{slug}', function ($slug) {

    $jobsPage = Entry::findByUri('/jobs');

    abort_unless($jobsPage, 404);

    $jobs = collect($jobsPage->get('jobs'));

    $job = $jobs->first(function ($set) use ($slug) {
        return ($set['job_slug'] ?? null) === $slug;
    });

    abort_unless($job, 404);

    return view('jobs.show', [
        'job' => $job,
    ]);

})->name('jobs.show');