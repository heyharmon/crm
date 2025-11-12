<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class OrganizationExportController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'organization_ids' => 'required|array|min:1',
            'organization_ids.*' => 'integer|exists:organizations,id',
        ]);

        $ids = collect($validated['organization_ids'])
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'message' => 'No organizations were provided for export.',
            ], 400);
        }

        $organizations = Organization::withTrashed()
            ->with('category')
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();

        if ($organizations->isEmpty()) {
            return response()->json([
                'message' => 'No organizations found to export.',
            ], 404);
        }

        // Define all columns from the organizations table
        $headers = [
            'id',
            'name',
            'type',
            'google_place_id',
            'source',
            'banner',
            'score',
            'reviews',
            'street',
            'city',
            'state',
            'country',
            'website',
            'website_crawl_status',
            'website_crawl_message',
            'redirects_to',
            'cms',
            'website_status',
            'website_redesign_status',
            'website_redesign_status_message',
            'phone',
            'charter_number',
            'is_low_income',
            'members',
            'assets',
            'loans',
            'deposits',
            'roaa',
            'net_worth_ratio',
            'loan_to_share_ratio',
            'deposit_growth',
            'loan_growth',
            'asset_growth',
            'member_growth',
            'net_worth_growth',
            'organization_category_id',
            'category_name',
            'map_url',
            'notes',
            'last_major_redesign_at',
            'last_major_redesign_at_actual',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        // Generate CSV content
        $callback = function () use ($organizations, $headers) {
            $file = fopen('php://output', 'w');

            // Write headers
            fputcsv($file, $headers);

            // Write data rows
            foreach ($organizations as $organization) {
                $row = [
                    $organization->id,
                    $organization->name,
                    $organization->type,
                    $organization->google_place_id,
                    $organization->source,
                    $organization->banner,
                    $organization->score,
                    $organization->reviews,
                    $organization->street,
                    $organization->city,
                    $organization->state,
                    $organization->country,
                    $organization->website,
                    $organization->website_crawl_status,
                    $organization->website_crawl_message,
                    $organization->redirects_to,
                    $organization->cms,
                    $organization->website_status,
                    $organization->website_redesign_status,
                    $organization->website_redesign_status_message,
                    $organization->phone,
                    $organization->charter_number,
                    $organization->is_low_income ? '1' : '0',
                    $organization->members,
                    $organization->assets,
                    $organization->loans,
                    $organization->deposits,
                    $organization->roaa,
                    $organization->net_worth_ratio,
                    $organization->loan_to_share_ratio,
                    $organization->deposit_growth,
                    $organization->loan_growth,
                    $organization->asset_growth,
                    $organization->member_growth,
                    $organization->net_worth_growth,
                    $organization->organization_category_id,
                    $organization->category?->name,
                    $organization->map_url,
                    $organization->notes,
                    $organization->last_major_redesign_at?->format('Y-m-d'),
                    $organization->last_major_redesign_at_actual?->format('Y-m-d'),
                    $organization->created_at?->format('Y-m-d H:i:s'),
                    $organization->updated_at?->format('Y-m-d H:i:s'),
                    $organization->deleted_at?->format('Y-m-d H:i:s'),
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        };

        $filename = 'organizations_export_' . date('Y-m-d_His') . '.csv';

        return Response::stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
