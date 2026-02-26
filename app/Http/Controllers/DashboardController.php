<?php

namespace App\Http\Controllers;

use App\Services\CurrentCompany;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly CurrentCompany $currentCompany,
    ) {}

    public function index(Request $request)
    {
        $selectedCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($selectedCompanyId === null, 403, 'No company selected');

        $payload = $this->dashboardService->getDashboardStats($selectedCompanyId);

        return Inertia::render('Dashboard', [
            'stats' => $payload['stats'],
            'recentUsers' => $payload['recentUsers'],
        ]);
    }
}
