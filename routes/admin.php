<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BatchController;
use App\Http\Controllers\Admin\BatchScheduleController;
use App\Http\Controllers\Admin\CompetencyController;
use App\Http\Controllers\Admin\CompetencyDirectoryController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\RubricSetController;
use App\Http\Controllers\Admin\ScoringController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserPasswordController;
use App\Http\Controllers\Admin\UserStatusController;
use App\Http\Controllers\DataExportController;
use App\Http\Controllers\OrganizationController;
use App\Http\Middleware\EnsureOptionalFeatureEnabled;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('settings', [SystemSettingsController::class, 'index'])
            ->name('settings.index');
        Route::patch('settings', [SystemSettingsController::class, 'update'])
            ->name('settings.update');
        Route::patch('settings/policies', [SystemSettingsController::class, 'updatePolicies'])
            ->name('settings.policies.update');
        Route::post('settings/test-email', [SystemSettingsController::class, 'testEmail'])
            ->middleware('throttle:3,1')
            ->name('settings.test-email');
        Route::post('settings/test-ai', [SystemSettingsController::class, 'testAi'])
            ->middleware('throttle:5,1')
            ->name('settings.test-ai');
        Route::delete('settings/demo-data', [SystemSettingsController::class, 'destroyDemoData'])
            ->middleware('throttle:3,1')
            ->name('settings.demo-data.destroy');

        Route::get('roles', [RolePermissionController::class, 'index'])
            ->name('roles.index');
        Route::put('roles/{role}/permissions', [RolePermissionController::class, 'update'])
            ->name('roles.permissions.update');

        Route::get('scoring/export', [DataExportController::class, 'scoring'])->middleware(EnsureOptionalFeatureEnabled::class.':chapter_ten_scoring')->name('scoring.export');
        Route::resource('scoring', ScoringController::class)->only(['index', 'show', 'store'])->parameters(['scoring' => 'scoringRun'])->middleware(EnsureOptionalFeatureEnabled::class.':chapter_ten_scoring');
        Route::post('scoring/{scoringRun}/approve', [ScoringController::class, 'approve'])->middleware(EnsureOptionalFeatureEnabled::class.':chapter_ten_scoring')->name('scoring.approve');
        Route::post('scoring/{scoringRun}/request-revision', [ScoringController::class, 'requestRevision'])->middleware(EnsureOptionalFeatureEnabled::class.':chapter_ten_scoring')->name('scoring.request-revision');
        Route::resource('rubrics', RubricSetController::class)->only(['index', 'store', 'update'])->parameters(['rubrics' => 'rubricSet'])->middleware(EnsureOptionalFeatureEnabled::class.':chapter_ten_scoring');
        Route::post('rubrics/{rubricSet}/versions', [RubricSetController::class, 'createVersion'])->middleware(EnsureOptionalFeatureEnabled::class.':chapter_ten_scoring')->name('rubrics.versions.store');
        Route::post('rubrics/{rubricSet}/versions/{rubricVersion}/publish', [RubricSetController::class, 'publish'])->middleware(EnsureOptionalFeatureEnabled::class.':chapter_ten_scoring')->name('rubrics.versions.publish');

        Route::get('proposals', fn () => to_route('approval.index'))->name('proposals.index');

        Route::resource('batches', BatchController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['batches' => 'batch']);
        Route::get('batches/{batch}/schedules', [BatchScheduleController::class, 'index'])->name('batches.schedules.index');
        Route::post('batches/{batch}/schedules', [BatchScheduleController::class, 'store'])->name('batches.schedules.store');
        Route::patch('batches/{batch}/schedules/{schedule}', [BatchScheduleController::class, 'update'])->name('batches.schedules.update');

        Route::resource('organizations', OrganizationController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::resource('competencies', CompetencyController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['competencies' => 'competency']);
        Route::get('competencies/template', [CompetencyController::class, 'template'])
            ->name('competencies.template');
        Route::post('competencies/import', [CompetencyController::class, 'import'])
            ->name('competencies.import');

        Route::get('competency-directory', [CompetencyDirectoryController::class, 'index'])
            ->name('competency-directory.index');

        Route::get('audit', [AuditLogController::class, 'index'])
            ->name('audit.index');

        Route::patch('users/bulk-status', [UserController::class, 'bulkStatus'])
            ->name('users.bulk-status.update');
        Route::delete('users/bulk', [UserController::class, 'bulkDestroy'])
            ->name('users.bulk.destroy');
        Route::resource('users', UserController::class)
            ->only(['index', 'store', 'update']);
        Route::put('users/{user}/password', [UserPasswordController::class, 'update'])
            ->name('users.password.update');
        Route::patch('users/{user}/status', [UserStatusController::class, 'update'])
            ->name('users.status.update');
    });
