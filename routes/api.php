<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/webhook/n8n/{categoria}', [\App\Http\Controllers\N8nWebhookController::class, 'handle'])
    ->middleware(\App\Http\Middleware\VerifyN8nToken::class);

Route::post('/login', [AuthController::class, 'login']);

Route::get('/me', [AuthController::class, 'me'])->middleware('checkrole');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('checkrole');
Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('checkrole');

Route::get('/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'stats'])
    ->middleware('checkrole:gerente,operativo');

Route::get('/history/{categoria}', [\App\Http\Controllers\HistoryController::class, 'index'])
    ->middleware('checkrole:gerente,operativo');

Route::patch('/history/{categoria}/{id}', [\App\Http\Controllers\HistoryController::class, 'updateRecord'])
    ->middleware('checkrole:gerente,operativo');

Route::get('/history/{categoria}/export', [\App\Http\Controllers\HistoryController::class, 'export'])
    ->middleware('checkrole:gerente');

Route::get('/sectores', \App\Http\Controllers\SectorController::class)->middleware('checkrole');

Route::get('/logs', [\App\Http\Controllers\SystemLogController::class, 'index'])
    ->middleware('checkrole:gerente');

Route::post('/logs/{id}/retry', [\App\Http\Controllers\SystemLogController::class, 'retry'])
    ->middleware('checkrole:gerente');

Route::get('/uploads/pending-count', [\App\Http\Controllers\ClientUploadController::class, 'pendingCount'])
    ->middleware('checkrole:gerente,operativo');

Route::prefix('uploads')->middleware('checkrole')->group(function () {
    Route::get('/', [\App\Http\Controllers\ClientUploadController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\ClientUploadController::class, 'store'])->middleware('checkrole:cliente');
    Route::get('/{id}/download', [\App\Http\Controllers\ClientUploadController::class, 'download'])->middleware('checkrole:operativo,gerente');
    Route::post('/{id}/validate', [\App\Http\Controllers\ClientUploadController::class, 'validateUpload'])->middleware('checkrole:operativo');
});

Route::get('/debug-passport', function (Illuminate\Http\Request $request) {
    return [
        'user' => $request->user('api') ? $request->user('api')->email : 'NADIE',
        'auth_header' => $request->header('Authorization'),
        'all_headers' => $request->headers->all(),
        'guard_api_driver' => config('auth.guards.api.driver'),
    ];
});

use App\Http\Controllers\ContableImportController;
use App\Http\Controllers\ContableController;

Route::prefix('contable')->middleware('checkrole:gerente,operativo')->group(function () {
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

Route::prefix('planilla')->middleware('checkrole:gerente,operativo')->group(function () {
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
