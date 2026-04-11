<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\ReportsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function __construct(private ReportsService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('access-reports');

        $days = (int) $request->get('days', 30);
        $days = in_array($days, [7, 14, 30, 90]) ? $days : 30;

        return Inertia::render('Reports/Index', [
            'stats' => $this->service->stats($request->user()->workspace_id, $days),
            'days'  => $days,
        ]);
    }
}
