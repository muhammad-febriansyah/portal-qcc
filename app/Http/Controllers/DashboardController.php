<?php

namespace App\Http\Controllers;

use App\Services\AdminDashboardOverview;
use App\Services\MemberDashboardOverview;
use App\Services\ProgramDashboardOverview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
}
