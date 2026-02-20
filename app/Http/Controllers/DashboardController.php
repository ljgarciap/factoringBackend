<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats()
    {
        // 1. CARTERA STATS
        $carteraQuery = \App\Models\OperacionCartera::query();
        $totalCarteraCount = (clone $carteraQuery)->count();
        $totalSaldoCapital = (clone $carteraQuery)->sum('saldo_capital');
        $uniqueClients = (clone $carteraQuery)->distinct('identificacion')->count();
        $movsPerClient = $uniqueClients > 0 ? round($totalCarteraCount / $uniqueClients, 1) : 0;
        
        // Mora Calculation
        $moraQuery = (clone $carteraQuery)->where('dias_vencido', '>', 0);
        $totalMoraVal = $moraQuery->sum('valor_vencido');
        $moraIndex = $totalSaldoCapital > 0 ? ($totalMoraVal / $totalSaldoCapital) * 100 : 0;
        
        $currentDebtors = (clone $carteraQuery)->where('valor_mora', '>', 0)
            ->select('cliente', 'identificacion', 'valor_mora', 'dias_vencido')
            ->orderBy('valor_mora', 'desc')
            ->limit(10)
            ->get();

        // City Distribution
        $sectoresCiudades = \App\Models\OperacionCartera::select('ciudad', \Illuminate\Support\Facades\DB::raw('SUM(saldo_capital) as total'))
            ->groupBy('ciudad')
            ->orderBy('total', 'desc')
            ->get();

        // Amortization Plan Distribution
        $amortizacion = \App\Models\OperacionCartera::select('plan_amortizacion', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->groupBy('plan_amortizacion')
            ->get();

        // Daily Disbursements (Last 30 days)
        $dailyDisbursements = \App\Models\OperacionCartera::select('fecha_desembolso', \Illuminate\Support\Facades\DB::raw('SUM(valor_desembolso) as total'))
            ->whereNotNull('fecha_desembolso')
            ->groupBy('fecha_desembolso')
            ->orderBy('fecha_desembolso', 'desc')
            ->limit(15)
            ->get();

        // 2. FACTORING STATS (OP & PAGOS)
        $factoringOpCount = \App\Models\OperacionFactoring::count();
        $totalExposure = \App\Models\OperacionFactoring::sum('monto');
        
        $pagosCount = \App\Models\PagoFactoring::count();
        $totalCollected = \App\Models\PagoFactoring::sum('monto_pagado');
        
        // Detailed Payment Breakdown (Daily Capital vs Interest)
        // For Factoring, we'll treat 'valor_nominal' as Capital and 'descuento_financiero' as Interest
        $dailyPayments = \App\Models\PagoFactoring::select(
                'fecha_pago',
                \Illuminate\Support\Facades\DB::raw('SUM(valor_nominal) as capital'),
                \Illuminate\Support\Facades\DB::raw('SUM(descuento_financiero) as intereses'),
                \Illuminate\Support\Facades\DB::raw('SUM(monto_pagado) as total')
            )
            ->groupBy('fecha_pago')
            ->orderBy('fecha_pago', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'cartera' => [
                'count' => $totalCarteraCount,
                'unique_clients' => $uniqueClients,
                'movs_per_client' => $movsPerClient,
                'saldo_capital' => (float)$totalSaldoCapital,
                'mora_index' => round($moraIndex, 2),
                'ciudades' => $sectoresCiudades,
                'amortizacion' => $amortizacion,
                'debtors' => $currentDebtors,
                'daily_disbursements' => $dailyDisbursements
            ],
            'factoring' => [
                'op_count' => $factoringOpCount,
                'exposure' => (float)$totalExposure,
                'pagos_count' => $pagosCount,
                'total_collected' => (float)$totalCollected,
                'daily_payments' => $dailyPayments
            ],
            'confirming' => [
                'count' => \App\Models\OperacionConfirming::count(),
                'total_val' => (float)\App\Models\OperacionConfirming::sum('valor_nominal'),
                'avg_tasa' => round(\App\Models\OperacionConfirming::avg('tasa_factor'), 2)
            ],
        ]);
    }
}
