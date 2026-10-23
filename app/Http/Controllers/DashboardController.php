<?php

namespace App\Http\Controllers;

use App\Services\AdminDashboardOverview;
use App\Services\RoleDashboardOverview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, AdminDashboardOverview $overview, RoleDashboardOverview $roleOverview): Response
    {
        $user = $request->user();

        return Inertia::render('dashboard', [
            'adminOverview' => $user->can('dashboard.admin') ? $overview->get() : null,
            'roleOverview' => $user->can('dashboard.admin') ? null : $roleOverview->get($user),
        ]);
    }
}
