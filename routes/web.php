<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\WebsiteModuleController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    Route::post('/websites', [WebsiteController::class, 'store'])->name('websites.store');
    Route::get('/websites/{website}', [WebsiteController::class, 'show'])->name('websites.show');
    Route::delete('/websites/{website}', [WebsiteController::class, 'destroy'])->name('websites.destroy');
    Route::post('/websites/{website}/analyze', [WebsiteController::class, 'analyze'])->name('websites.analyze');

    Route::get('/websites/{website}/predictive', [WebsiteModuleController::class, 'predictive'])->name('websites.predictive');
    Route::get('/websites/{website}/budget', [WebsiteModuleController::class, 'budget'])->name('websites.budget');
    Route::post('/websites/{website}/budget-scenarios', [WebsiteModuleController::class, 'storeScenario'])->name('websites.budget-scenarios.store');
    Route::delete('/websites/{website}/budget-scenarios/{scenario}', [WebsiteModuleController::class, 'destroyScenario'])->name('websites.budget-scenarios.destroy');
    Route::get('/websites/{website}/wasted-spend', [WebsiteModuleController::class, 'wastedSpend'])->name('websites.wasted-spend');
    Route::get('/websites/{website}/lead-intelligence', [WebsiteModuleController::class, 'lead'])->name('websites.lead');
    Route::get('/websites/{website}/creative', [WebsiteModuleController::class, 'creative'])->name('websites.creative');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
