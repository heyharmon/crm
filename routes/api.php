<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationBatchActionController;
use App\Http\Controllers\OrganizationCategoryController;
use App\Http\Controllers\WebsiteRatingController;
use App\Http\Controllers\OrganizationGoogleMapsScraperController;
use App\Http\Controllers\OrganizationHubspotImportController;
use App\Http\Controllers\NCUAImportController;
use App\Http\Controllers\WebsitePageScraperController;
use App\Http\Controllers\WebsiteRatingOptionController;
use App\Http\Controllers\OrganizationWebsiteRedesignController;
use App\Http\Controllers\OrganizationCmsDetectionController;
use App\Http\Controllers\OrganizationWebsiteStatusController;
use App\Http\Controllers\DashboardController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/invitations/verify', [InvitationController::class, 'verify']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Guest-accessible routes (website ratings)
    Route::get('website-ratings', [WebsiteRatingController::class, 'index']);
    Route::post('organizations/{organization}/website-ratings', [WebsiteRatingController::class, 'store']);
    Route::delete('organizations/{organization}/website-ratings', [WebsiteRatingController::class, 'destroy']);
    Route::get('website-rating-options', [WebsiteRatingOptionController::class, 'index']);

    // Guest-accessible organization viewing
    Route::get('organizations', [OrganizationController::class, 'index']);
    Route::get('organizations/{organization}', [OrganizationController::class, 'show']);

    // Admin-only routes
    Route::middleware('admin')->group(function () {
        Route::get('/dashboard', DashboardController::class);

        // User routes
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{user}', [UserController::class, 'show']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
        Route::get('invitations', [InvitationController::class, 'index']);
        Route::post('invitations', [InvitationController::class, 'store']);

        // Organization management routes
        Route::post('organizations', [OrganizationController::class, 'store']);
        Route::put('organizations/{organization}', [OrganizationController::class, 'update']);
        Route::patch('organizations/{organization}', [OrganizationController::class, 'update']);
        Route::delete('organizations/{organization}', [OrganizationController::class, 'destroy']);
        Route::post('organizations/{id}/restore', [OrganizationController::class, 'restore']);
        Route::post('organizations/batch/actions', OrganizationBatchActionController::class);

        // Import routes
        Route::post('organizations/import/hubspot', [OrganizationHubspotImportController::class, 'store']);
        Route::post('organizations/import/ncua', [NCUAImportController::class, 'store']);

        // Organization category routes
        Route::delete('organization-categories/bulk', [OrganizationCategoryController::class, 'bulkDestroy']);
        Route::resource('organization-categories', OrganizationCategoryController::class)->only([
            'index',
            'store',
            'update',
            'destroy'
        ]);

        // Website rating options (admin-only write operations)
        Route::post('website-rating-options', [WebsiteRatingOptionController::class, 'store']);
        Route::put('website-rating-options/{website_rating_option}', [WebsiteRatingOptionController::class, 'update']);
        Route::patch('website-rating-options/{website_rating_option}', [WebsiteRatingOptionController::class, 'update']);
        Route::delete('website-rating-options/{website_rating_option}', [WebsiteRatingOptionController::class, 'destroy']);

        // Organization enhancement routes
        Route::post('organizations/{organization}/website-redesigns', [OrganizationWebsiteRedesignController::class, 'store']);
        Route::post('organizations/{organization}/cms-detections', [OrganizationCmsDetectionController::class, 'store']);
        Route::post('organizations/{organization}/website-status-check', [OrganizationWebsiteStatusController::class, 'store']);

        // Google Maps scraper routes
        Route::prefix('google-maps-scraper')->group(function () {
            Route::post('start', [OrganizationGoogleMapsScraperController::class, 'startImport']);
            Route::get('runs', [OrganizationGoogleMapsScraperController::class, 'getImports']);
            Route::get('runs/{apifyRun}', [OrganizationGoogleMapsScraperController::class, 'getImport']);
        });

        // Web scraper routes
        Route::prefix('web-scraper')->group(function () {
            Route::post('start', [WebsitePageScraperController::class, 'startScraping']);
        });
    });
});
