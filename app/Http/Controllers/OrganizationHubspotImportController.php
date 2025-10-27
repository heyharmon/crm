<?php

namespace App\Http\Controllers;

use App\Services\Imports\HubspotOrganizationImportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class OrganizationHubspotImportController extends Controller
{
    public function __construct(private readonly HubspotOrganizationImportService $importService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        try {
            $result = $this->importService->import($validated['file']);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            Log::error('HubSpot organization import failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to process the uploaded HubSpot export.',
            ], 422);
        }

        return response()->json($result);
    }
}
