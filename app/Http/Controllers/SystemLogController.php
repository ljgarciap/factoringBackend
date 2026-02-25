<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SystemLogController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/logs",
     *     summary="Listar logs de auditoría del sistema",
     *     tags={"Logs"},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Búsqueda en logs",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Lista paginada de logs")
     * )
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $sortBy = $request->query('sortBy', 'id');
        $sortDir = $request->query('sortDir', 'desc');
        $perPage = $request->query('perPage', 15);

        $query = \App\Models\SystemLog::query();

        if (!empty($search)) {
            $table = (new \App\Models\SystemLog)->getTable();
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
