<?php

namespace App\Http\Controllers;

use App\Models\QccBatch;
use App\Services\AdminDashboardOverview;
use App\Services\AuditService;
use App\Services\ExcelExportService;
use App\Services\MemberDashboardOverview;
use App\Services\ProgramDashboardDetails;
use App\Services\ProgramDashboardOverview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DashboardController extends Controller
{
    /**
     * SIMers and Team Leaders get their personal dashboard. Owner QCC, Mentors and admins
     * get the program dashboard of the running batch, and admins the system overview too.
     */
    public function index(Request $request, AdminDashboardOverview $overview, MemberDashboardOverview $memberOverview, ProgramDashboardOverview $programOverview): Response
    {
        $user = $request->user();
        $program = $programOverview->get(
            $user->visibleProgramPhases(),
            ownProjectsOf: $user->seesAllProgramProjects() ? null : $user->id,
        );

        if ($user->usesMemberDashboard()) {
            return Inertia::render('member-dashboard', [
                'overview' => $memberOverview->get($user),
                'program' => $program,
            ]);
        }

        return Inertia::render('dashboard', [
            'adminOverview' => $user->can('dashboard.admin') ? $overview->get() : null,
            'program' => $program,
        ]);
    }

    /**
     * The records behind one number of the program dashboard, for its detail popup.
     */
    public function programDetails(Request $request, ProgramDashboardOverview $programOverview, ProgramDashboardDetails $details): JsonResponse
    {
        [$batch, $metric, $key] = $this->programDetailRequest($request, $programOverview, $details);

        return response()->json($details->get($request->user(), $batch, $metric, $key));
    }

    /**
     * The same list as the detail popup, as an Excel file.
     */
    public function exportProgramDetails(
        Request $request,
        ProgramDashboardOverview $programOverview,
        ProgramDashboardDetails $details,
        ExcelExportService $excel,
        AuditService $audit,
    ): BinaryFileResponse {
        [$batch, $metric, $key] = $this->programDetailRequest($request, $programOverview, $details);
        $detail = $details->get($request->user(), $batch, $metric, $key);
        $sheet = $details->spreadsheet($detail);

        return $excel->download(
            'dashboard_'.$metric,
            'dashboard-'.Str::slug($detail['title']).'.xlsx',
            mb_strtoupper($detail['title']),
            sprintf('%s · %s · Diekspor %s', $batch->name, $detail['description'], now()->format('d M Y, H:i')),
            $sheet['headers'],
            $sheet['rows'],
            count($sheet['rows']),
            ['batch_id' => $batch->id, 'metric' => $metric, 'key' => $key],
            $audit,
            $detail['title'],
        );
    }

    /**
     * The batch, metric and key of a detail request, once the viewer may read that metric.
     *
     * @return array{0: QccBatch, 1: string, 2: string|null}
     */
    private function programDetailRequest(Request $request, ProgramDashboardOverview $programOverview, ProgramDashboardDetails $details): array
    {
        $validated = $request->validate([
            'metric' => ['required', 'string', Rule::in(array_keys(ProgramDashboardDetails::METRICS))],
            'key' => ['nullable', 'string', 'max:100'],
        ]);

        $batch = $programOverview->currentBatch();

        abort_if($batch === null, 404);
        abort_unless($details->canView($request->user(), $batch, $validated['metric']), 403);

        return [$batch, $validated['metric'], $validated['key'] ?? null];
    }
}
