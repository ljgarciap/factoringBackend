<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// PARCHE: Soporte para X-Authorization (Bypass de LiteSpeed/Shared Hosting)
\Illuminate\Support\Facades\Request::macro('bearerToken', function () {
    $header = $this->header('Authorization') ?: $this->header('X-Authorization');
    if (str_starts_with($header ?? '', 'Bearer ')) {
        return mb_substr($header, 7);
    }
    return $this->query('token');
});

Route::post('/webhook/n8n/{categoria}', [\App\Http\Controllers\N8nWebhookController::class, 'handle'])
    ->middleware(\App\Http\Middleware\VerifyN8nToken::class);

use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'stats'])
        ->middleware('role:gerente,operativo');

    Route::get('/history/{categoria}', [\App\Http\Controllers\HistoryController::class, 'index'])
        ->middleware('role:gerente,operativo');

    Route::patch('/history/{categoria}/{id}', [\App\Http\Controllers\HistoryController::class, 'updateRecord'])
        ->middleware('role:gerente,operativo');

    Route::get('/history/{categoria}/export', [\App\Http\Controllers\HistoryController::class, 'export'])
        ->middleware('role:gerente');

    Route::get('/sectores', \App\Http\Controllers\SectorController::class);
    
    Route::get('/logs', [\App\Http\Controllers\SystemLogController::class, 'index'])
        ->middleware('role:gerente');

    Route::post('/logs/{id}/retry', [\App\Http\Controllers\SystemLogController::class, 'retry'])
        ->middleware('role:gerente');

    // --- Client Upload Module ---
    Route::prefix('uploads')->group(function () {
        Route::get('/pending-count', [\App\Http\Controllers\ClientUploadController::class, 'pendingCount'])
            ->middleware('role:gerente,operativo');
        Route::get('/', [\App\Http\Controllers\ClientUploadController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\ClientUploadController::class, 'store'])->middleware('role:cliente');
        Route::get('/{id}/download', [\App\Http\Controllers\ClientUploadController::class, 'download'])->middleware('role:operativo,gerente');
        Route::post('/{id}/validate', [\App\Http\Controllers\ClientUploadController::class, 'validateUpload'])->middleware('role:operativo');
        Route::post('/{id}/approve', [\App\Http\Controllers\ClientUploadController::class, 'approveUpload'])->middleware('role:gerente');
    });
});

// (Asegurado) Endpoint POST agregado para reintentos de auditoría


use App\Http\Controllers\ContableImportController;
use App\Http\Controllers\ContableController;

Route::prefix('contable')->middleware(['auth:sanctum', 'role:gerente,operativo'])->group(function () {
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

Route::prefix('planilla')->middleware(['auth:sanctum', 'role:gerente,operativo'])->group(function () {
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

// RUTA DE PRUEBA: Diagnóstico de autenticación
Route::get('/debug-header', function (Illuminate\Http\Request $request) {
    $authHeader = $request->header('Authorization');
    $token = str_replace('Bearer ', '', $authHeader);
    
    return [
        'authorization_header' => $authHeader,
        'token_extracted' => $token,
        'all_headers' => $request->headers->all(),
        'token_found_in_db' => \Laravel\Sanctum\PersonalAccessToken::findToken($token) ? 'SÍ' : 'NO',
        'app_url' => config('app.url'),
        'sanctum_stateful' => config('sanctum.stateful'),
    ];
});

// RUTA DE EMERGENCIA: Para ver por qué da error 500
Route::get('/debug-log', function () {
    $logPath = storage_path('logs/laravel.log');
    if (!file_exists($logPath)) return "No hay archivo de logs.";
    $lines = file($logPath);
    return array_reverse(array_slice($lines, -100)); // Últimas 100 líneas
});

Route::get('/debug-users', function () {
    return \App\Models\User::all(['id', 'name', 'email', 'roles']);
});
