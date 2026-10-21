<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MyCompetencyController;
use App\Http\Controllers\ProposalController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('proposals', [ProposalController::class, 'index'])->name('proposals.index');
    Route::get('proposals/{project}/candidates', [ProposalController::class, 'candidates'])->name('proposals.candidates');

    Route::resource('competencies', MyCompetencyController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['competencies' => 'memberCompetency'])
        ->names('my-competencies');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
