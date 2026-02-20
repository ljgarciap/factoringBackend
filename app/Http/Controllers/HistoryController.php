<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request, $categoria)
    {
        $search = $request->query('search');
        $sortBy = $request->query('sortBy', 'id');
        $sortDir = $request->query('sortDir', 'desc');
        $perPage = $request->query('perPage', 15);

        $modelClass = null;
        switch ($categoria) {
            case 'cartera':
                $modelClass = \App\Models\OperacionCartera::class;
                break;
            case 'op':
                $modelClass = \App\Models\OperacionFactoring::class;
                break;
            case 'pagos':
                $modelClass = \App\Models\PagoFactoring::class;
                break;
            case 'opf':
                $modelClass = \App\Models\OperacionConfirming::class;
                break;
            default:
                return response()->json(['message' => 'Categoría no válida'], 400);
        }

        $query = $modelClass::query();

        if (!empty($search)) {
            $table = (new $modelClass)->getTable();
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
            
            $query->where(function($q) use ($columns, $search) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'LIKE', "%{$search}%");
                }
            });
        }

        return response()->json($query->orderBy($sortBy, $sortDir)->paginate($perPage));
    }
    public function updateRecord(Request $request, $categoria, $id)
    {
        $modelClass = null;
        switch ($categoria) {
            case 'cartera': $modelClass = \App\Models\OperacionCartera::class; break;
            case 'op': $modelClass = \App\Models\OperacionFactoring::class; break;
            case 'pagos': $modelClass = \App\Models\PagoFactoring::class; break;
            case 'opf': $modelClass = \App\Models\OperacionConfirming::class; break;
            default: return response()->json(['message' => 'Categoría no válida'], 400);
        }

        $record = $modelClass::findOrFail($id);
        $data = $request->only(['observaciones', 'sector_economico', 'ciudad']);
        
        // Logical Learning: If the user manually fixes a sector, we "learn" it.
        if (isset($data['sector_economico']) && $categoria === 'cartera' && !empty($record->actividad_economica)) {
            \App\Models\SectorMapping::updateOrCreate(
                ['actividad_economica' => $record->actividad_economica],
                ['sector' => $data['sector_economico']]
            );
        }

        $record->update($data);

        return response()->json(['message' => 'Registro actualizado', 'data' => $record]);
    }

    public function export(Request $request, $categoria)
    {
        $search = $request->query('search');
        $modelClass = null;
        switch ($categoria) {
            case 'cartera': $modelClass = \App\Models\OperacionCartera::class; break;
            case 'op': $modelClass = \App\Models\OperacionFactoring::class; break;
            case 'pagos': $modelClass = \App\Models\PagoFactoring::class; break;
            case 'opf': $modelClass = \App\Models\OperacionConfirming::class; break;
            default: abort(400);
        }

        $query = $modelClass::query();
        if (!empty($search)) {
            $table = (new $modelClass)->getTable();
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
            $query->where(function($q) use ($columns, $search) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'LIKE', "%{$search}%");
                }
            });
        }

        $records = $query->get();
        $filename = "export_{$categoria}_" . date('Ymd_His') . ".csv";
        
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($records) {
            $file = fopen('php://output', 'w');
            if ($records->count() > 0) {
                fputcsv($file, array_keys($records[0]->getAttributes()));
                foreach ($records as $row) {
                    fputcsv($file, array_values($row->getAttributes()));
                }
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
