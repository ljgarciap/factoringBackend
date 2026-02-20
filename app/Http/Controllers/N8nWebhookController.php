<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OperacionCartera;
use App\Models\OperacionFactoring;
use App\Models\PagoFactoring;
use App\Models\OperacionConfirming;
use App\Models\SystemLog;

class N8nWebhookController extends Controller
{
    public function handle(Request $request, $categoria)
    {
        $data = $request->input('data', []);
        $filename = $request->input('filename') ?? $request->query('filename');
        
        // Si mandan un solo objeto en vez de array, lo convertimos a array.
        if (!is_array(reset($data)) && count($data) > 0) {
            $data = [$data];
        }

        // Extraemos 'filename' de los datos y lo quitamos para no insertarlo en los modelos
        foreach ($data as $key => $row) {
            if (isset($row['filename'])) {
                if (empty($filename)) {
                    $filename = $row['filename'];
                }
                unset($data[$key]['filename']);
            }
        }

        try {
            switch ($categoria) {
                case 'cartera':
                    foreach ($data as $row) {
                        OperacionCartera::create($row);
                    }
                    break;
                case 'op':
                    foreach ($data as $row) {
                        OperacionFactoring::create($row);
                    }
                    break;
                case 'pagos':
                    foreach ($data as $row) {
                        PagoFactoring::create($row);
                    }
                    break;
                case 'opf':
                    foreach ($data as $row) {
                        OperacionConfirming::create($row);
                    }
                    break;
                default:
                    return response()->json(['message' => 'Categoría no válida'], 400);
            }

            SystemLog::create([
                'categoria' => $categoria,
                'filename' => $filename,
                'action' => 'Webhook Recibido',
                'message' => 'Lote de datos procesado con éxito.',
                'records_processed' => count($data)
            ]);

            return response()->json(['message' => 'Datos procesados correctamente']);
            
        } catch (\Exception $e) {
            SystemLog::create([
                'categoria' => $categoria,
                'filename' => $filename,
                'action' => 'Error en Webhook',
                'message' => 'Fallo al procesar: ' . $e->getMessage(),
                'records_processed' => 0
            ]);
            
            return response()->json(['message' => 'Error procesando datos', 'error' => $e->getMessage()], 500);
        }
    }
}
