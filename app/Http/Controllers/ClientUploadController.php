<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ClientUpload;
use Illuminate\Support\Facades\Storage;

class ClientUploadController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = ClientUpload::with(['user', 'validator', 'approver']);

        // Roles Filtering
        if (in_array('cliente', $user->roles)) {
            $query->where('user_id', $user->id);
        }

        // Search Filtering
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                  ->orWhereHas('user', function($qu) use ($search) {
                      $qu->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Status Filtering
        if ($request->has('status') && $request->status !== 'todos') {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('perPage', 10);
        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB limit
        ]);

        $file = $request->file('file');
        $path = $file->store('client_uploads');

        $upload = ClientUpload::create([
            'user_id' => $request->user()->id,
            'filename' => $path,
            'original_name' => $file->getClientOriginalName(),
            'status' => 'pendiente',
        ]);

        return response()->json($upload);
    }

    public function validateUpload(Request $request, $id)
    {
        $upload = ClientUpload::findOrFail($id);
        
        $request->validate([
            'observations' => 'nullable|string',
            'action' => 'required|in:validar,rechazar'
        ]);

        if ($request->action === 'validar') {
            $upload->update([
                'status' => 'validado',
                'observations' => $request->observations,
                'validated_by' => $request->user()->id
            ]);
        } else {
            $upload->update([
                'status' => 'rechazado',
                'observations' => $request->observations,
                'validated_by' => $request->user()->id
            ]);
        }

        return response()->json($upload);
    }

    public function approveUpload(Request $request, $id)
    {
        $upload = ClientUpload::findOrFail($id);
        
        $request->validate([
            'action' => 'required|in:aprobar,rechazar',
            'observations' => 'nullable|string'
        ]);

        if ($upload->status !== 'validado') {
            return response()->json(['message' => 'Solo se pueden procesar archivos que ya han sido validados por el operativo.'], 422);
        }

        if ($request->action === 'aprobar') {
            $upload->update([
                'status' => 'aprobado',
                'observations' => $request->observations ?: $upload->observations,
                'approved_by' => $request->user()->id
            ]);
        } else {
            $upload->update([
                'status' => 'rechazado',
                'observations' => $request->observations ?: $upload->observations,
                'approved_by' => $request->user()->id
            ]);
        }

        return response()->json($upload);
    }

    public function pendingCount(Request $request)
    {
        $user = $request->user();
        $operativoCount = ClientUpload::where('status', 'pendiente')->count();
        $gerenteCount = ClientUpload::where('status', 'validado')->count();

        return response()->json([
            'operativo' => $operativoCount,
            'gerente' => $gerenteCount,
            'total' => $operativoCount + $gerenteCount
        ]);
    }

    public function download($id)
    {
        $upload = ClientUpload::findOrFail($id);
        
        if (!Storage::exists($upload->filename)) {
            return response()->json(['message' => 'Archivo no encontrado físicamente en el servidor.'], 404);
        }

        return Storage::download($upload->filename, $upload->original_name);
    }
}
