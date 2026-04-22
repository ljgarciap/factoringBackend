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
        
        if ($upload->status !== 'validado') {
            return response()->json(['message' => 'Solo se pueden aprobar archivos validados.'], 422);
        }

        $upload->update([
            'status' => 'aprobado',
            'approved_by' => $request->user()->id
        ]);

        return response()->json($upload);
    }

    public function pendingCount(Request $request)
    {
        $user = $request->user();
        $count = 0;

        // Note: The frontend sends the active_role in a header if we want, 
        // but here we can just check what's pending in general or based on roles.
        
        $operativoCount = ClientUpload::where('status', 'pendiente')->count();
        $gerenteCount = ClientUpload::where('status', 'validado')->count();

        return response()->json([
            'operativo' => $operativoCount,
            'gerente' => $gerenteCount,
            'total' => $operativoCount + $gerenteCount
        ]);
    }
}
