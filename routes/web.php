<?php

use App\Http\Controllers\MyCompetencyController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('competencies', MyCompetencyController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['competencies' => 'memberCompetency'])
        ->names('my-competencies');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
