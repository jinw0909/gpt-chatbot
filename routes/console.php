<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->everyMinute()->sendOutputTo(storage_path('logs/inspire.log'));

Artisan::command('app:clear-logs', function () {
    $files = File::files(storage_path('logs'));

    foreach ($files as $file) {
        file_put_contents($file, '');
        $this->info("Cleared: " . $file->getFilename());
    }

    $this->info('All log files have been cleared.');
})->purpose('Clear all log files')->daily();
