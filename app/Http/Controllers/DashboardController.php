<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard/Index',
            $this->dashboardService->getDashboardData($request->user()->id)
        );
    }
}
