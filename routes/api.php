<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhook/n8n/{categoria}', [\App\Http\Controllers\N8nWebhookController::class, 'handle'])
    ->middleware(\App\Http\Middleware\VerifyN8nToken::class);
Route::get('/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'stats']);
Route::get('/history/{categoria}', [\App\Http\Controllers\HistoryController::class, 'index']);
Route::patch('/history/{categoria}/{id}', [\App\Http\Controllers\HistoryController::class, 'updateRecord']);
Route::get('/history/{categoria}/export', [\App\Http\Controllers\HistoryController::class, 'export']);
Route::get('/sectores', \App\Http\Controllers\SectorController::class);
Route::get('/logs', [\App\Http\Controllers\SystemLogController::class, 'index']);
