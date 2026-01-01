<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Hotel;
use App\Models\StaffUser;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $stats = [
            'admins' => AdminUser::count(),
            'hotels' => Hotel::count(),
            'staff' => StaffUser::count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
