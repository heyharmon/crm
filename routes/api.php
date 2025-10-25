<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationCategoryController;
use App\Http\Controllers\WebsiteRatingController;
use App\Http\Controllers\OrganizationGoogleMapsScraperController;
use App\Http\Controllers\WebsitePageController;
use App\Http\Controllers\WebsiteRatingOptionController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/invitations/verify', [InvitationController::class, 'verify']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Team routes
    Route::resource('teams', TeamController::class);
    Route::post('teams/{team}/invite', [TeamController::class, 'invite']);
    Route::post('teams/{team}/accept-invitation', [TeamController::class, 'acceptInvitation']);
    Route::post('teams/{team}/decline-invitation', [TeamController::class, 'declineInvitation']);
    Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember']);
    Route::put('teams/{team}/members/{user}/role', [TeamController::class, 'updateMemberRole']);

    // Organization routes
    Route::resource('organizations', OrganizationController::class);
    Route::post('organizations/{id}/restore', [OrganizationController::class, 'restore']);

    // Organization category routes
    // Define bulk route BEFORE resource to avoid matching {organization-category}='bulk'
    Route::delete('organization-categories/bulk', [OrganizationCategoryController::class, 'bulkDestroy']);
    Route::resource('organization-categories', OrganizationCategoryController::class)->only([
        'index', 'store', 'update', 'destroy'
    ]);

    // Website rating options
    Route::resource('website-rating-options', WebsiteRatingOptionController::class)->only([
        'index', 'store', 'update', 'destroy'
    ]);

    // Organization website ratings
    Route::post('organizations/{organization}/website-ratings', [WebsiteRatingController::class, 'store']);
    Route::delete('organizations/{organization}/website-ratings', [WebsiteRatingController::class, 'destroy']);

    // Google Maps scraper routes
    Route::prefix('google-maps-scraper')->group(function () {
        Route::post('start', [OrganizationGoogleMapsScraperController::class, 'startImport']);
        Route::get('runs', [OrganizationGoogleMapsScraperController::class, 'getImports']);
        Route::get('runs/{apifyRun}', [OrganizationGoogleMapsScraperController::class, 'getImport']);
    });

    // Web scraper routes
    Route::prefix('web-scraper')->group(function () {
        Route::post('start', [WebsitePageController::class, 'startScraping']);
        Route::get('runs', [WebsitePageController::class, 'getScrapingRuns']);
        Route::get('runs/{apifyRun}', [WebsitePageController::class, 'getScrapingRun']);
    });
});
