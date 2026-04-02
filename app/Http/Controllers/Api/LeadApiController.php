<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DealNote;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Services\DealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LeadApiController extends Controller
{
    public function store(Request $request, DealService $dealService): JsonResponse
    {
        // Validate API token
        $apiToken = config('services.lead_api.token');

        if ($apiToken && $request->header('X-API-Token') !== $apiToken) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'mensaje' => 'nullable|string',
            'origen' => 'nullable|string|max:50',
            'organization_slug' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Datos inválidos',
                'detalles' => $validator->errors(),
            ], 422);
        }

        try {
            // Find the organization (by slug or default to first active)
            $organization = $request->organization_slug
                ? Organization::where('slug', $request->organization_slug)->where('status', 'active')->first()
                : Organization::where('status', 'active')->first();

            if (!$organization) {
                return response()->json(['error' => 'Organización no encontrada'], 404);
            }

            // Find the default pipeline for this organization
            $pipeline = Pipeline::withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('is_default', true)
                ->first();

            if (!$pipeline) {
                $pipeline = Pipeline::withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->first();
            }

            if (!$pipeline) {
                return response()->json(['error' => 'No hay pipeline configurado'], 500);
            }

            // Create the deal in the first stage (Nuevo)
            $deal = $dealService->createDeal([
                'organization_id' => $organization->id,
                'pipeline_id' => $pipeline->id,
                'contact_name' => $request->nombre,
                'contact_email' => $request->email,
                'contact_phone' => $request->telefono,
                'value' => 0,
            ]);

            // Add the message as a note if provided
            $origen = $request->origen ?? 'web';
            $mensaje = $request->mensaje;

            if ($mensaje || $origen) {
                $noteContent = '';
                if ($origen) {
                    $noteContent .= "Origen: {$origen}\n";
                }
                if ($mensaje) {
                    $noteContent .= "\n{$mensaje}";
                }

                DealNote::create([
                    'organization_id' => $organization->id,
                    'deal_id' => $deal->id,
                    'user_id' => null,
                    'body' => trim($noteContent),
                ]);
            }

            Log::info('Nuevo lead recibido desde web', [
                'deal_id' => $deal->id,
                'organization_id' => $organization->id,
                'contact_name' => $request->nombre,
                'origen' => $origen,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lead creado exitosamente',
                'deal_id' => $deal->id,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al crear lead desde web', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'Error al procesar la solicitud',
            ], 500);
        }
    }
}
