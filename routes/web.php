<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MainController;
use App\Http\Controllers\OpenAIController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\TestController;



Route::get('/', function () {
    return view('welcome');
});

Route::get('/main', [MainController::class, 'index']);

Route::get('/test', [MainController::class, 'test']);

Route::get('/openai', [OpenAIController::class, 'openAI']);

Route::post('/process-message', [MessageController::class, 'processMessage'])->name('process-message');

Route::get('/assistants', [OpenAIController::class, 'assistantList']);

Route::get('/messages', [OpenAIController::class, 'messageList']);

Route::get('/user/{id}', [UserController::class, 'getUserInfo']);

Route::get('/test-log', function() {
   Log::info("Test log message");
   return 'Log test completed';
});

Route::get('/test/rds', [TestController::class, 'connectAWS']);
Route::get('/test/rds2', [TestController::class, 'connectRetri']);

Route::post('/user/{id}/add-token', [TokenController::class, 'addToken']);
Route::post('/user/{id}/add-charge', [TokenController::class, 'addCharge']);
Route::post('/user/{id}/reduce-token', [TokenController::class, 'reduceToken']);
Route::post('/user/{id}/reduce-charge', [TokenController::class, 'reduceCharge']);
Route::get('/user/{id}/get-token', [TokenController::class, 'getToken']);
Route::get('/user/{id}/get-charge', [TokenController::class, 'getCharge']);
