<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats()
    {
        $totalCartera = \App\Models\OperacionCartera::count();
        $totalOp = \App\Models\OperacionFactoring::count();
        $totalPagos = \App\Models\PagoFactoring::count();
        $totalConfirming = \App\Models\OperacionConfirming::count();

        return response()->json([
            'total_cartera' => $totalCartera,
            'total_factoring_op' => $totalOp,
            'total_factoring_pagos' => $totalPagos,
            'total_confirming' => $totalConfirming,
        ]);
    }
}
