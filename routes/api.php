<?php

use App\Http\Controllers\Api\AnalysisJobApiController;
use App\Http\Controllers\Api\CrawlerCallbackController;
use App\Http\Controllers\Api\ModuleIntelligenceApiController;
use App\Http\Controllers\Api\ProjectApiController;
use App\Http\Controllers\Api\ReportApiController;
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
    Route::get('/websites/{website}/campaign-intelligence', [WebsiteApiController::class, 'campaignIntelligence'])
        ->middleware('throttle:60,1');
    Route::get('/websites/{website}/predictive-analytics', [ModuleIntelligenceApiController::class, 'predictiveAnalytics'])
        ->middleware('throttle:60,1');
    Route::get('/websites/{website}/lead-intelligence', [ModuleIntelligenceApiController::class, 'leadIntelligence'])
        ->middleware('throttle:60,1');
    Route::get('/websites/{website}/wasted-spend', [ModuleIntelligenceApiController::class, 'wastedSpend'])
        ->middleware('throttle:60,1');
    Route::get('/websites/{website}/creative-intelligence', [ModuleIntelligenceApiController::class, 'creativeIntelligence'])
        ->middleware('throttle:60,1');
    Route::get('/websites/{website}/marketing-advisor', [ModuleIntelligenceApiController::class, 'marketingAdvisor'])
        ->middleware('throttle:60,1');
    Route::get('/websites/{website}/reports', [ReportApiController::class, 'index'])
        ->name('api.reports.index')
        ->middleware('throttle:60,1');
    Route::post('/websites/{website}/reports', [ReportApiController::class, 'store'])
        ->name('api.reports.store')
        ->middleware('throttle:20,1');
    Route::get('/reports/{report}', [ReportApiController::class, 'show'])
        ->name('api.reports.show')
        ->middleware('throttle:60,1');
    Route::get('/reports/{report}/download', [ReportApiController::class, 'download'])
        ->name('api.reports.download')
        ->middleware('throttle:30,1');
    Route::get('/budget-channels', [ModuleIntelligenceApiController::class, 'channelFactors'])
        ->middleware('throttle:60,1');
    Route::get('/websites/{website}/budget-intelligence', [ModuleIntelligenceApiController::class, 'budgetScenariosIndex'])
        ->middleware('throttle:60,1');
    Route::get('/websites/{website}/budget-scenarios', [ModuleIntelligenceApiController::class, 'budgetScenariosIndex'])
        ->middleware('throttle:60,1');
    Route::post('/websites/{website}/budget-scenarios', [ModuleIntelligenceApiController::class, 'budgetScenariosStore'])
        ->middleware('throttle:20,1');

    Route::get('/analysis-jobs/{analysis_job}', [AnalysisJobApiController::class, 'show'])
        ->middleware('throttle:60,1');
});
