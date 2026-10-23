<?php

use App\Http\Controllers\BiweeklyReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataExportController;
use App\Http\Controllers\MentoringController;
use App\Http\Controllers\MyCompetencyController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectDirectoryController;
use App\Http\Controllers\ProjectEvidenceController;
use App\Http\Controllers\ProjectRequirementController;
use App\Http\Controllers\ProjectRiskBoardController;
use App\Http\Controllers\ProjectStepController;
use App\Http\Controllers\ProjectStepWorkflowController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\ProposalWizardController;
use App\Http\Controllers\ReviewApprovalController;
use App\Http\Controllers\TeamFormationController;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified', 'profile.complete'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('qcc-projects', [ProjectDirectoryController::class, 'index'])->name('qcc-projects.index');
    Route::get('project-steps', [ProjectDirectoryController::class, 'steps'])->name('project-steps.index');
    Route::get('evidence-repository', [ProjectDirectoryController::class, 'evidence'])->name('evidence-repository.index');
    Route::get('biweekly-reports', [ProjectDirectoryController::class, 'biweekly'])->name('biweekly-directory.index');
    Route::get('mentoring-projects', [ProjectDirectoryController::class, 'mentoring'])->name('mentoring-directory.index');
    Route::get('project-risks', [ProjectDirectoryController::class, 'risks'])->name('risk-directory.index');
    Route::get('exports/proposals', [DataExportController::class, 'proposals'])->name('exports.proposals');
    Route::get('exports/qcc-projects', [DataExportController::class, 'projects'])->name('exports.qcc-projects');
    Route::get('exports/project-steps', [DataExportController::class, 'projectStepsDirectory'])->name('exports.project-steps');
    Route::get('exports/biweekly-reports', [DataExportController::class, 'biweeklyDirectory'])->name('exports.biweekly-reports');
    Route::get('exports/mentoring-reports', [DataExportController::class, 'mentoringDirectory'])->name('exports.mentoring-reports');
    Route::get('exports/project-risks', [DataExportController::class, 'risksDirectory'])->name('exports.project-risks');
    Route::get('team-formation', [TeamFormationController::class, 'index'])->name('team-formation.index');
    Route::get('approval', [ReviewApprovalController::class, 'index'])->name('approval.index');

    Route::get('proposals', [ProposalController::class, 'index'])->name('proposals.index');
    Route::get('proposals/{project}', [ProposalController::class, 'show'])
        ->whereNumber('project')
        ->name('proposals.show');
    Route::get('proposals/{project}/candidates', [ProposalController::class, 'candidates'])->name('proposals.candidates');
    Route::post('proposals/{project}/matching', [ProposalController::class, 'startMatching'])->whereNumber('project')->name('proposals.matching.start');
    Route::post('proposals/{project}/team', [ProposalController::class, 'confirmTeam'])->whereNumber('project')->name('proposals.team.confirm');
    Route::post('proposals/{project}/submit', [ProposalController::class, 'submit'])->whereNumber('project')->name('proposals.submit');
    Route::post('proposals/{project}/review', [ProposalController::class, 'startReview'])->whereNumber('project')->name('proposals.review.start');
    Route::post('proposals/{project}/review/decision', [ProposalController::class, 'decide'])->whereNumber('project')->name('proposals.review.decide');

    Route::get('projects/{project}/steps/{step?}', [ProjectStepController::class, 'show'])
        ->whereNumber(['project', 'step'])
        ->name('projects.steps.show');
    Route::get('projects/{project}/steps/export', [DataExportController::class, 'projectSteps'])
        ->whereNumber('project')->name('projects.steps.export');

    Route::get('projects/{project}/evidence', [ProjectEvidenceController::class, 'index'])
        ->whereNumber('project')
        ->name('projects.evidence.index');
    Route::post('projects/{project}/evidence', [ProjectEvidenceController::class, 'store'])
        ->whereNumber('project')
        ->name('projects.evidence.store');
    Route::get('projects/{project}/evidence/{evidence}/preview', [ProjectEvidenceController::class, 'preview'])
        ->whereNumber(['project', 'evidence'])
        ->name('projects.evidence.preview');
    Route::get('projects/{project}/evidence/{evidence}/thumbnail', [ProjectEvidenceController::class, 'thumbnail'])
        ->whereNumber(['project', 'evidence'])
        ->name('projects.evidence.thumbnail');
    Route::get('projects/{project}/evidence/{evidence}/download', [ProjectEvidenceController::class, 'download'])
        ->whereNumber(['project', 'evidence'])
        ->name('projects.evidence.download');

    Route::prefix('projects/{project}/steps/{step}')->whereNumber(['project', 'step'])->name('projects.steps.')->group(function () {
        Route::patch('/', [ProjectStepWorkflowController::class, 'update'])->name('update');
        Route::post('submit', [ProjectStepWorkflowController::class, 'submit'])->name('submit');
        Route::post('review', [ProjectStepWorkflowController::class, 'startReview'])->name('review.start');
        Route::post('approve', [ProjectStepWorkflowController::class, 'approve'])->name('approve');
        Route::post('revision', [ProjectStepWorkflowController::class, 'requestRevision'])->name('revision');
    });

    Route::prefix('projects/{project}/risks')->whereNumber('project')->name('projects.risks.')->group(function () {
        Route::get('/', [ProjectRiskBoardController::class, 'index'])->name('index');
        Route::get('export', [DataExportController::class, 'risks'])->name('export');
        Route::post('/', [ProjectRiskBoardController::class, 'storeRisk'])->name('store');
        Route::patch('{risk}', [ProjectRiskBoardController::class, 'updateRisk'])->whereNumber('risk')->name('update');
        Route::delete('{risk}', [ProjectRiskBoardController::class, 'destroyRisk'])->whereNumber('risk')->name('destroy');
        Route::post('actions', [ProjectRiskBoardController::class, 'storeAction'])->name('actions.store');
        Route::patch('actions/{action}', [ProjectRiskBoardController::class, 'updateAction'])->whereNumber('action')->name('actions.update');
        Route::delete('actions/{action}', [ProjectRiskBoardController::class, 'destroyAction'])->whereNumber('action')->name('actions.destroy');
    });

    Route::prefix('projects/{project}/biweekly')->whereNumber('project')->name('projects.biweekly.')->group(function () {
        Route::get('/', [BiweeklyReportController::class, 'index'])->name('index');
        Route::get('export', [DataExportController::class, 'biweekly'])->name('export');
        Route::post('/', [BiweeklyReportController::class, 'store'])->name('store');
        Route::patch('{report}', [BiweeklyReportController::class, 'update'])->whereNumber('report')->name('update');
        Route::post('{report}/submit', [BiweeklyReportController::class, 'submit'])->whereNumber('report')->name('submit');
        Route::post('{report}/approve', [BiweeklyReportController::class, 'approve'])->whereNumber('report')->name('approve');
        Route::post('{report}/revision', [BiweeklyReportController::class, 'revision'])->whereNumber('report')->name('revision');
        Route::delete('{report}', [BiweeklyReportController::class, 'destroy'])->whereNumber('report')->name('destroy');
    });

    Route::prefix('projects/{project}/mentoring')->whereNumber('project')->name('projects.mentoring.')->group(function () {
        Route::get('/', [MentoringController::class, 'index'])->name('index');
        Route::get('export', [DataExportController::class, 'mentoring'])->name('export');
        Route::post('assignments', [MentoringController::class, 'assign'])->name('assign');
        Route::delete('assignments/{assignment}', [MentoringController::class, 'endAssignment'])->whereNumber('assignment')->name('assignments.end');
        Route::post('/', [MentoringController::class, 'store'])->name('store');
        Route::patch('{report}', [MentoringController::class, 'update'])->whereNumber('report')->name('update');
        Route::post('{report}/submit', [MentoringController::class, 'submit'])->whereNumber('report')->name('submit');
        Route::post('{report}/acknowledge', [MentoringController::class, 'acknowledge'])->whereNumber('report')->name('acknowledge');
    });

    Route::resource('competencies', MyCompetencyController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['competencies' => 'memberCompetency'])
        ->names('my-competencies');

    Route::get('proposals/create', [ProposalWizardController::class, 'create'])
        ->can('create', Project::class)
        ->name('proposals.create');
    Route::post('proposals', [ProposalWizardController::class, 'store'])
        ->name('proposals.store');
    Route::get('proposals/{project}/edit', [ProposalWizardController::class, 'edit'])
        ->whereNumber('project')
        ->name('proposals.edit');
    Route::patch('proposals/{project}', [ProposalWizardController::class, 'update'])
        ->whereNumber('project')
        ->name('proposals.update');
    Route::put('proposals/{project}/requirements', [ProjectRequirementController::class, 'update'])
        ->whereNumber('project')
        ->name('proposals.requirements.update');
    Route::post('proposals/{project}/attachments', [ProposalWizardController::class, 'storeAttachments'])
        ->whereNumber('project')
        ->name('proposals.attachments.store');
    Route::delete('proposals/{project}/attachments/{attachment}', [ProposalWizardController::class, 'destroyAttachment'])
        ->whereNumber('project')
        ->name('proposals.attachments.destroy');
    Route::get('proposals/{project}/attachments/{attachment}/preview', [ProposalWizardController::class, 'previewAttachment'])
        ->whereNumber(['project', 'attachment'])
        ->name('proposals.attachments.preview');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
