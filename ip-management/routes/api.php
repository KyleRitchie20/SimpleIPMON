<?php

use App\Http\Controllers\Api\ClientController;
use Illuminate\Support\Facades\Route;

Route::post('/install', [ClientController::class, 'install']);
Route::post('/heartbeat', [ClientController::class, 'heartbeat'])->name('api.heartbeat');
Route::get('/ping', fn() => response()->json(['status' => 'pong']));

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/clients', [ClientController::class, 'listClients']);
    Route::get('/clients/{id}/metrics', [ClientController::class, 'getMetrics']);
    Route::delete('/clients/{id}', [ClientController::class, 'deleteClient']);
});
