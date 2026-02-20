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
        
        // Ranked Clients (Saldos Actuales Operación)
        $clientRanking = (clone $carteraQuery)
            ->select('cliente', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_ops'), \Illuminate\Support\Facades\DB::raw('SUM(saldo_capital) as saldo_total'))
            ->groupBy('cliente')
            ->orderBy('saldo_total', 'desc')
            ->limit(10)
            ->get();

        // Debtors in Mora
        $currentDebtors = (clone $carteraQuery)->where('valor_mora', '>', 0)
            ->select('cliente', 'identificacion', 'valor_mora', 'dias_vencido')
            ->orderBy('valor_mora', 'desc')
            ->limit(10)
            ->get();

        // City Distribution
        // Daily Disbursements Breakdown (Reporte de Desembolsos)
        // Group by parsed date and client to ensure unique rows
        $dailyDisbursements = (clone $carteraQuery)
            ->selectRaw("
                CASE 
                    WHEN fecha_desembolso LIKE '%/%' THEN DATE(STR_TO_DATE(fecha_desembolso, '%d/%m/%Y'))
                    ELSE DATE(COALESCE(fecha_desembolso, created_at))
                END as fecha, 
                cliente, 
                SUM(valor_desembolso) as total
            ")
            ->groupBy(\Illuminate\Support\Facades\DB::raw("
                CASE 
                    WHEN fecha_desembolso LIKE '%/%' THEN DATE(STR_TO_DATE(fecha_desembolso, '%d/%m/%Y'))
                    ELSE DATE(COALESCE(fecha_desembolso, created_at))
                END
            "), 'cliente')
            ->orderBy('fecha', 'desc')
            ->get();

        $sectoresCiudades = (clone $carteraQuery)
            ->select('ciudad', \Illuminate\Support\Facades\DB::raw('SUM(saldo_capital) as total'))
            ->groupBy('ciudad')
            ->orderBy('total', 'desc')
            ->get();

        // Activity Distribution (Actividad Económica por Saldo Capital)
        $actividadEconomica = (clone $carteraQuery)
            ->select('sector_economico', \Illuminate\Support\Facades\DB::raw('SUM(saldo_capital) as total'))
            ->groupBy('sector_economico')
            ->orderBy('total', 'desc')
            ->get();

        // Amortization Plan Distribution
        $amortizacion = (clone $carteraQuery)
            ->select('plan_amortizacion', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->groupBy('plan_amortizacion')
            ->get();



        // 2. FACTORING STATS (OP & PAGOS)
        $factoringOpQuery = $applyFilters(\App\Models\OperacionFactoring::query(), 'created_at');
        $factoringOpCount = (clone $factoringOpQuery)->count();
        $totalFinanced = (clone $factoringOpQuery)->sum('monto');
        $totalDisbursed = (clone $factoringOpQuery)->sum('valor_desembolsado');
        $totalReserva = (clone $factoringOpQuery)->sum('valor_reserva');
        $avgTasaFactoring = (clone $factoringOpQuery)->avg('tasa_descuento');
        
        // Exposición por Pagador
        $exposureByPayer = (clone $factoringOpQuery)
            ->select('pagador', \Illuminate\Support\Facades\DB::raw('SUM(monto) as total'))
            ->groupBy('pagador')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        // Vencimientos Factoring
        $vencimientos = (clone $factoringOpQuery)
            ->select('pagador', 'fecha_vencimiento', 'monto')
            ->orderBy('fecha_vencimiento', 'asc')
            ->whereNotNull('fecha_vencimiento')
            ->limit(10)
            ->get()
            ->map(function($v) {
                $today = now();
                $venc = \Illuminate\Support\Carbon::parse($v->fecha_vencimiento);
                $diff = $today->diffInDays($venc, false);
                
                return [
                    'pagador' => $v->pagador,
                    'fecha' => $v->fecha_vencimiento,
                    'monto' => $v->monto,
                    'dias' => (int)$diff,
                    'estado' => $diff < 0 ? 'Vencido' : ($diff <= 15 ? 'Por Vencer' : 'Vigente')
                ];
            });

        $pagosQuery = $applyFilters(\App\Models\PagoFactoring::query(), 'created_at');
        $pagosCount = (clone $pagosQuery)->count();
        $totalCollected = (clone $pagosQuery)->sum('monto_pagado');
        $efficiencyScore = (clone $pagosQuery)->avg('dias_cartera');
        $earlyPaymentCost = (clone $pagosQuery)->sum('descuento_financiero');
        $outstandingBalance = (clone $pagosQuery)->sum('saldo_restante');
        
        // Monto Pagado a lo largo del tiempo (Timeline)
        $paymentTimeline = (clone $pagosQuery)
            ->selectRaw("
                CASE 
                    WHEN fecha_pago LIKE '%/%' THEN DATE(STR_TO_DATE(fecha_pago, '%d/%m/%Y'))
                    ELSE DATE(COALESCE(fecha_pago, created_at))
                END as fecha, 
                SUM(monto_pagado) as total
            ")
            ->groupBy(\Illuminate\Support\Facades\DB::raw("
                CASE 
                    WHEN fecha_pago LIKE '%/%' THEN DATE(STR_TO_DATE(fecha_pago, '%d/%m/%Y'))
                    ELSE DATE(COALESCE(fecha_pago, created_at))
                END
            "))
            ->orderBy('fecha', 'asc')
            ->limit(30)
            ->get();

        // Distribución de Pagos por Cliente
        $paymentDistribution = (clone $pagosQuery)
            ->select('cliente', \Illuminate\Support\Facades\DB::raw('SUM(monto_pagado) as total'))
            ->groupBy('cliente')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        // Registro de Pagos (Table)
        $paymentEntries = (clone $pagosQuery)
            ->select('cliente', 'factura_nro', 'dias_cartera', 'monto_pagado')
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        // Daily Payments Breakdown (Table from previous task, keeping it for history)
        $dailyPayments = (clone $pagosQuery)
            ->selectRaw("
                CASE 
                    WHEN fecha_pago LIKE '%/%' THEN DATE(STR_TO_DATE(fecha_pago, '%d/%m/%Y'))
                    ELSE DATE(COALESCE(fecha_pago, created_at))
                END as fecha, 
                cliente, 
                SUM(valor_nominal) as capital, 
                SUM(descuento_financiero) as intereses, 
                SUM(monto_pagado) as total
            ")
            ->groupBy(\Illuminate\Support\Facades\DB::raw("
                CASE 
                    WHEN fecha_pago LIKE '%/%' THEN DATE(STR_TO_DATE(fecha_pago, '%d/%m/%Y'))
                    ELSE DATE(COALESCE(fecha_pago, created_at))
                END
            "), 'cliente')
            ->orderBy('fecha', 'desc')
            ->limit(30)
            ->get();

        // 3. CONFIRMING STATS
        $confirmingQuery = $applyFilters(\App\Models\OperacionConfirming::query(), 'created_at');
        $confirmingCount = (clone $confirmingQuery)->count();
        $totalConfirmingVal = (clone $confirmingQuery)->sum('valor_nominal');
        $totalRendimientos = (clone $confirmingQuery)->sum('rendimientos_proyectados');
        $totalPagarDeudores = (clone $confirmingQuery)->sum('valor_pagar_deudor');
        $avgTasaConfirming = (clone $confirmingQuery)->avg('tasa_factor');

        // Análisis de Emisores (Pie Chart based on Valor Nominal)
        $analysisEmitters = (clone $confirmingQuery)
            ->select('emisor', \Illuminate\Support\Facades\DB::raw('SUM(valor_nominal) as total'))
            ->groupBy('emisor')
            ->orderBy('total', 'desc')
            ->get();

        // Tabla de Vencimientos y Días
        $vencimientosConfirming = (clone $confirmingQuery)
            ->select('id_titulo', 'emisor', 'fecha_final', 'dias')
            ->orderBy('fecha_final', 'asc')
            ->limit(15)
            ->get()
            ->map(function($v) {
                // Parse "dd/mm/yyyy" to standard format if needed
                $fecha = $v->fecha_final;
                if (strpos($fecha, '/') !== false) {
                    try {
                        $fecha = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $fecha)->format('Y-m-d');
                    } catch (\Exception $e) {}
                }
                return [
                    'id_titulo' => $v->id_titulo,
                    'emisor' => $v->emisor,
                    'fecha_final' => $fecha,
                    'dias' => $v->dias
                ];
            });

        // Gráfico de Barras de Tasa Media (by Emisor)
        $avgTasaByEmisor = (clone $confirmingQuery)
            ->select('emisor', \Illuminate\Support\Facades\DB::raw('AVG(tasa_factor) as avg_tasa'))
            ->groupBy('emisor')
            ->orderBy('avg_tasa', 'desc')
            ->get();

        // Rendimientos por Emisor
        $rendimientosByEmisor = (clone $confirmingQuery)
            ->select('emisor')
            ->selectRaw('SUM(valor_nominal) as total_nominal')
            ->selectRaw('SUM(rendimientos_proyectados) as total_rendimientos')
            ->groupBy('emisor')
            ->get()
            ->map(function($r) {
                return [
                    'emisor' => $r->emisor,
                    'valor_nominal' => (float)$r->total_nominal,
                    'rendimientos' => (float)$r->total_rendimientos
                ];
            });

        return response()->json([
            'cartera' => [
                'count' => $totalCarteraCount,
                'unique_clients' => $uniqueClients,
                'movs_per_client' => $movsPerClient,
                'saldo_capital' => (float)$totalSaldoCapital,
                'mora_index' => round($moraIndex, 4), // Higher precision for dashboard reference
                'total_mora' => (float)$totalMoraVal,
                'ciudades' => $sectoresCiudades,
                'actividad' => $actividadEconomica,
                'amortizacion' => $amortizacion,
                'client_ranking' => $clientRanking,
                'debtors' => $currentDebtors,
                'daily_disbursements' => $dailyDisbursements
            ],
            'factoring' => [
                'op_count' => $factoringOpCount,
                'volumen_total' => (float)$totalFinanced,
                'valor_desembolsado' => (float)$totalDisbursed,
                'valor_reserva' => (float)$totalReserva,
                'avg_tasa' => round($avgTasaFactoring, 2),
                'pagos_count' => $pagosCount,
                'total_collected' => (float)$totalCollected,
                'efficiency_score' => round($efficiencyScore, 1),
                'early_payment_cost' => (float)$earlyPaymentCost,
                'outstanding_balance' => (float)$outstandingBalance,
                'daily_payments' => $dailyPayments,
                'payment_timeline' => $paymentTimeline,
                'payment_distribution' => $paymentDistribution,
                'payment_entries' => $paymentEntries,
                'exposure_by_payer' => $exposureByPayer,
                'vencimientos' => $vencimientos
            ],
            'confirming' => [
                'count' => $confirmingCount,
                'total_val' => (float)$totalConfirmingVal,
                'rendimientos_proyectados' => (float)$totalRendimientos,
                'total_pagar_deudores' => (float)$totalPagarDeudores,
                'avg_tasa' => round($avgTasaConfirming, 2),
                'analisis_emisores' => $analysisEmitters,
                'vencimientos' => $vencimientosConfirming,
                'tasa_media_emisor' => $avgTasaByEmisor,
                'rendimientos_emisor' => $rendimientosByEmisor
            ],
        ]);
    }
}
