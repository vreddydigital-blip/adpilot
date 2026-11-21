<?php

use App\Http\Controllers\Api\AnalysisJobApiController;
use App\Http\Controllers\Api\CrawlerCallbackController;
use App\Http\Controllers\Api\ProjectApiController;
use App\Http\Controllers\Api\WebsiteApiController;
use Illuminate\Support\Facades\Route;

Route::post('/internal/crawler-callback', [CrawlerCallbackController::class, 'handle'])
    ->middleware('throttle:60,1');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', fn (\Illuminate\Http\Request $request) => $request->user());

    Route::apiResource('projects', ProjectApiController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::post('/websites', [WebsiteApiController::class, 'store']);
    Route::get('/websites/{website}', [WebsiteApiController::class, 'show']);
    Route::post('/websites/{website}/analyze', [WebsiteApiController::class, 'analyze'])
        ->middleware('throttle:20,1');

    Route::get('/analysis-jobs/{analysis_job}', [AnalysisJobApiController::class, 'show'])
        ->middleware('throttle:60,1');
});
