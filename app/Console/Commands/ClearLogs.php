<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the contents of all log files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        $files = File::files(storage_path('logs'));

        foreach ($files as $file) {
            // Truncate the log file
            file_put_contents($file, '');
            $this->info("Cleared: " . $file);
        }

        $this->info('All log files have been cleared.');
    }
}
