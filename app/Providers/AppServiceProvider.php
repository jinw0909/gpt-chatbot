<?php

namespace App\Providers;

use App\Services\CryptoService;
use App\Services\MessageProcessingService;
use App\Services\TokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use OpenAI\Client;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(TokenService::class, function($app) {
            return new TokenService();
        });

        $this->app->singleton(CryptoService::class, function($app) {
            return new CryptoService();
        });

        $this->app->singleton(MessageProcessingService::class, function($app) {
            return new MessageProcessingService($app->make(TokenService::class), $app->make(CryptoService::class));
        });

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
//        DB::connection('mysql2')->listen(function ($query) {
//            Log::info('Executed Query: ' . $query->sql, ['bindings' => $query->bindings, 'time' => $query->time]);
//        });
    }
}
