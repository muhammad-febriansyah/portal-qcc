<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\QccBatch;
use App\Services\AdminDashboardOverview;
use App\Services\MemberDashboardOverview;
use App\Services\ProgramDashboardOverview;
use App\Services\RoleDashboardOverview;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, AdminDashboardOverview $overview, RoleDashboardOverview $roleOverview, MemberDashboardOverview $memberOverview, ProgramDashboardOverview $programOverview): Response
    {
        $user = $request->user();

        if ($user->usesMemberDashboard()) {
            return Inertia::render('member-dashboard', [
                'overview' => $memberOverview->get($user),
                'program' => $programOverview->get($user->visibleProgramPhases(), ownProjectsOf: $user->seesAllProgramProjects() ? null : $user->id),
            ]);
        }

        $filters = $request->validate([
            'batch_id' => ['nullable', 'integer', 'exists:qcc_batches,id'],
            'period' => ['nullable', Rule::in(['all', '30', '90', '365'])],
        ]);
        $batchId = isset($filters['batch_id']) ? (int) $filters['batch_id'] : null;
        $selectedPeriod = $filters['period'] ?? 'all';
        $periodDays = $selectedPeriod === 'all' ? null : (int) $selectedPeriod;
        $visibleBatchIds = Project::query()->visibleTo($user)->select('batch_id')->distinct();

        return Inertia::render('dashboard', [
            'adminOverview' => $user->can('dashboard.admin') ? $overview->get() : null,
            'program' => $programOverview->get($user->visibleProgramPhases(), ownProjectsOf: $user->seesAllProgramProjects() ? null : $user->id),
            'roleOverview' => $roleOverview->get($user, $batchId, $periodDays),
            'selectedBatchId' => $batchId,
            'selectedPeriod' => $selectedPeriod,
            'batches' => QccBatch::query()
                ->whereIn('id', $visibleBatchIds)
                ->orderByDesc('starts_on')
                ->get(['id', 'name'])
                ->map(fn (QccBatch $batch): array => ['id' => $batch->id, 'name' => $batch->name])
                ->all(),
        ]);
    }
}
