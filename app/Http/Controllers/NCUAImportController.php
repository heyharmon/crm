<?php

namespace App\Http\Controllers;

use App\Services\Imports\NCUAImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NCUAImportController extends Controller
{
    public function __construct(private readonly NCUAImportService $importService)
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
            Log::error('NCUA organization import failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to process the uploaded NCUA export.',
            ], 422);
        }

        return response()->json($result);
    }
}
