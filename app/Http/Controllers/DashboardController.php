<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkShift;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'users' => User::count(),
            'employees' => Employee::count(),
            'companies' => Company::count(),
            'work_shifts' => WorkShift::count(),
        ];

        $recentUsers = User::latest()->limit(5)->get(['id', 'name', 'email', 'created_at']);

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentUsers' => $recentUsers,
        ]);
    }
}
