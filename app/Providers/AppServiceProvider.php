<?php

namespace App\Providers;

use App\Services\ArticleService;
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

        $this->app->singleton(ArticleService::class, function($app) {
            return new ArticleService();
        });

        $this->app->singleton(MessageProcessingService::class, function($app) {
            return new MessageProcessingService($app->make(TokenService::class), $app->make(CryptoService::class), $app->make(ArticleService::class));
        });



    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::listen(function ($query) {
            Log::info("SQL Query Executed: " . $query->sql);
            Log::info("Bindings: ", $query->bindings);
            Log::info("Time: " . $query->time . " ms");
        });
    }
}
