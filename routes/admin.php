<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BatchController;
use App\Http\Controllers\Admin\CompetencyController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\RubricSetController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserPasswordController;
use App\Http\Controllers\Admin\UserStatusController;
use App\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('roles', [RolePermissionController::class, 'index'])
            ->name('roles.index');

        Route::resource('rubrics', RubricSetController::class)
            ->only(['index', 'store', 'update'])
            ->parameters(['rubrics' => 'rubricSet']);

        Route::resource('batches', BatchController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['batches' => 'batch']);

        Route::resource('organizations', OrganizationController::class)
            ->only(['index', 'store', 'update']);

        Route::resource('competencies', CompetencyController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['competencies' => 'competency']);

        Route::get('audit', [AuditLogController::class, 'index'])
            ->name('audit.index');

        Route::resource('users', UserController::class)
            ->only(['index', 'store', 'update']);
        Route::put('users/{user}/password', [UserPasswordController::class, 'update'])
            ->name('users.password.update');
        Route::patch('users/{user}/status', [UserStatusController::class, 'update'])
            ->name('users.status.update');
    });
