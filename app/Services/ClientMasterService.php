<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\SystemLog;

class ClientMasterService
{
    /**
     * Master a client's data.
     * Returns the master identification for the operation.
     */
    public static function masterClient($nombre, $identificacion, $extraData = [])
    {
        if (empty($nombre) || empty($identificacion)) {
            return $identificacion;
        }

        // 1. Clean identification (numeric only)
        $nitBase = preg_replace('/[^0-9]/', '', $identificacion);
        
        // 2. Look up by name (Single Source of Truth by Name)
        $clientByName = Cliente::where('nombre', $nombre)->first();
        
        if ($clientByName) {
            // THE NAME IS ALREADY IN OUR MASTER LIST. 
            // We ignore whatever the AI read and use the Master NIT.
            if ($clientByName->identificacion !== $nitBase) {
                SystemLog::create([
                    'categoria' => 'validation',
                    'action' => 'Consensus Override',
                    'message' => "Client '{$nombre}' exists with NIT '{$clientByName->identificacion}'. Batch provided '{$nitBase}'. Using Master NIT.",
                    'records_processed' => 0
                ]);
            }
            return $clientByName->identificacion;
        }

        // 3. New name - Does the NIT already belong to someone else?
        $clientById = Cliente::where('identificacion', $nitBase)->first();
        if ($clientById) {
             SystemLog::create([
                'categoria' => 'validation',
                'action' => 'NIT Clash',
                'message' => "NIT '{$nitBase}' is already assigned to '{$clientById->nombre}'. Blocked creation for '{$nombre}'.",
                'records_processed' => 0
            ]);
            throw new \Exception("Conflicto de Identificación: El NIT {$nitBase} ya pertenece a '{$clientById->nombre}'.");
        }

        // 4. Completely new client
        Cliente::create([
            'nombre' => $nombre,
            'identificacion' => $nitBase,
            'ciudad' => $extraData['ciudad'] ?? null,
            'sector_economico' => $extraData['sector_economico'] ?? null,
            'actividad_economica' => $extraData['actividad_economica'] ?? null,
            'is_verified' => true, // Consensus-based creation is considered verified
            'verification_method' => 'consensus'
        ]);

        return $nitBase;
    }
}
