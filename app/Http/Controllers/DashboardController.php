<?php

namespace App\Http\Controllers;

use App\Services\AdminDashboardOverview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, AdminDashboardOverview $overview): Response
    {
        return Inertia::render('dashboard', [
            'adminOverview' => $request->user()->can('dashboard.admin') ? $overview->get() : null,
        ]);
    }
}
