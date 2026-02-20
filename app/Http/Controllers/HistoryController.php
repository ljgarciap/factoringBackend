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
}
