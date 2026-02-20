<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $fechaInicio = $request->query('fecha_inicio');
        $fechaFin = $request->query('fecha_fin');
        $cliente = $request->query('cliente');

        // Helper to apply filters
        $applyFilters = function($query, $dateField = null) use ($fechaInicio, $fechaFin, $cliente) {
            if ($cliente) {
                $table = $query->getModel()->getTable();
                $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
                
                $idCol = 'identificacion';
                if (in_array('nit_cliente', $columns)) $idCol = 'nit_cliente';
                elseif (in_array('nit', $columns)) $idCol = 'nit';
                elseif (in_array('emisor_nit', $columns)) $idCol = 'emisor_nit';

                $nameCol = 'cliente';
                if (!in_array('cliente', $columns) && in_array('emisor', $columns)) $nameCol = 'emisor';

                $query->where(function($q) use ($cliente, $idCol, $nameCol) {
                    $q->where($nameCol, 'LIKE', "%{$cliente}%")
                      ->orWhere($idCol, 'LIKE', "%{$cliente}%");
                });
            }
            if ($fechaInicio && $dateField) {
                if ($dateField === 'created_at') $query->whereDate($dateField, '>=', $fechaInicio);
                else $query->where($dateField, '>=', $fechaInicio);
            }
            if ($fechaFin && $dateField) {
                if ($dateField === 'created_at') $query->whereDate($dateField, '<=', $fechaFin);
                else $query->where($dateField, '<=', $fechaFin);
            }
            return $query;
        };

        // 1. CARTERA STATS
        $carteraQuery = $applyFilters(\App\Models\OperacionCartera::query(), 'created_at');
        $totalCarteraCount = (clone $carteraQuery)->count();
        $totalSaldoCapital = (clone $carteraQuery)->sum('saldo_capital');
        $uniqueClients = (clone $carteraQuery)->distinct('identificacion')->count();
        $movsPerClient = $uniqueClients > 0 ? round($totalCarteraCount / $uniqueClients, 1) : 0;
        
        // Mora Calculation
        $moraQuery = (clone $carteraQuery)->where('dias_vencido', '>', 0);
        $totalMoraVal = (clone $moraQuery)->sum('valor_vencido');
        $moraIndex = $totalSaldoCapital > 0 ? ($totalMoraVal / $totalSaldoCapital) * 100 : 0;
        
        $currentDebtors = (clone $carteraQuery)->where('valor_mora', '>', 0)
            ->select('cliente', 'identificacion', 'valor_mora', 'dias_vencido')
            ->orderBy('valor_mora', 'desc')
            ->limit(10)
            ->get();

        // City Distribution
        $sectoresCiudades = (clone $carteraQuery)
            ->select('ciudad', \Illuminate\Support\Facades\DB::raw('SUM(saldo_capital) as total'))
            ->groupBy('ciudad')
            ->orderBy('total', 'desc')
            ->get();

        // Amortization Plan Distribution
        $amortizacion = (clone $carteraQuery)
            ->select('plan_amortizacion', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->groupBy('plan_amortizacion')
            ->get();

        // Daily Disbursements (Last 15 days)
        $dailyDisbursements = (clone $carteraQuery)
            ->select('fecha_desembolso', \Illuminate\Support\Facades\DB::raw('SUM(valor_desembolso) as total'))
            ->whereNotNull('fecha_desembolso')
            ->groupBy('fecha_desembolso')
            ->orderBy('fecha_desembolso', 'desc')
            ->limit(15)
            ->get();

        // 2. FACTORING STATS (OP & PAGOS)
        $factoringOpQuery = $applyFilters(\App\Models\OperacionFactoring::query(), 'created_at');
        $factoringOpCount = (clone $factoringOpQuery)->count();
        $totalExposure = (clone $factoringOpQuery)->sum('monto');
        
        $pagosQuery = $applyFilters(\App\Models\PagoFactoring::query(), 'created_at');
        $pagosCount = (clone $pagosQuery)->count();
        $totalCollected = (clone $pagosQuery)->sum('monto_pagado');
        
        $dailyPayments = (clone $pagosQuery)
            ->select(
                'fecha_pago',
                \Illuminate\Support\Facades\DB::raw('SUM(valor_nominal) as capital'),
                \Illuminate\Support\Facades\DB::raw('SUM(descuento_financiero) as intereses'),
                \Illuminate\Support\Facades\DB::raw('SUM(monto_pagado) as total')
            )
            ->groupBy('fecha_pago')
            ->orderBy('fecha_pago', 'desc')
            ->limit(10)
            ->get();

        // 3. CONFIRMING STATS (Filtered as well)
        $confirmingQuery = $applyFilters(\App\Models\OperacionConfirming::query(), 'created_at');
        $confirmingCount = (clone $confirmingQuery)->count();
        $totalConfirmingVal = (clone $confirmingQuery)->sum('valor_nominal');
        $avgTasaConfirming = (clone $confirmingQuery)->avg('tasa_factor');

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
                'count' => $confirmingCount,
                'total_val' => (float)$totalConfirmingVal,
                'avg_tasa' => round($avgTasaConfirming, 2)
            ],
        ]);
    }
}
