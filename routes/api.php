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
Route::post('/logs/{id}/retry', [\App\Http\Controllers\SystemLogController::class, 'retry']);

// (Asegurado) Endpoint POST agregado para reintentos de auditoría


use App\Http\Controllers\ContableImportController;
use App\Http\Controllers\ContableController;

Route::prefix('contable')->group(function () {
    Route::post('/upload/{type}', [ContableImportController::class, 'upload']);
    Route::get('/facturas', [ContableController::class, 'getFacturas']);
    Route::get('/bancos', [ContableController::class, 'getBancos']);
    Route::get('/auxiliar', [ContableController::class, 'getAuxiliares']);
    Route::get('/gastos', [ContableController::class, 'getGastos']);
    Route::get('/imports', [ContableController::class, 'getImports']);
    Route::delete('/clear', [ContableController::class, 'clearAll']);
    Route::post('/reconcile', [App\Http\Controllers\ReconciliationController::class, 'reconcile']);
});

use App\Http\Controllers\PlanillaController;

Route::prefix('planilla')->group(function () {
    Route::get('/fincas', [PlanillaController::class, 'getFincas']);
    Route::post('/fincas', [PlanillaController::class, 'storeFinca']);
    
    Route::get('/trabajadores', [PlanillaController::class, 'getTrabajadores']);
    Route::post('/trabajadores', [PlanillaController::class, 'storeTrabajador']);
    
    Route::get('/labores', [PlanillaController::class, 'getLabores']);
    Route::post('/labores', [PlanillaController::class, 'storeLabor']);
    
    Route::get('/actividades', [PlanillaController::class, 'getActividades']);
    Route::post('/actividades', [PlanillaController::class, 'storeActividad']);
    Route::delete('/actividades/{id}', [PlanillaController::class, 'deleteActividad']);
    
    Route::get('/gastos', [PlanillaController::class, 'getGastos']);
    Route::post('/gastos', [PlanillaController::class, 'storeGasto']);
    
    Route::get('/resumen', [PlanillaController::class, 'getResumen']);
});
