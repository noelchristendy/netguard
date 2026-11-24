<?php

namespace App\Http\Controllers;

use App\Models\TrafficLog;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'allow' => TrafficLog::where('decision', 'allow')->count(),
            'deny' => TrafficLog::where('decision', 'deny')->count(),
            'logs' => TrafficLog::whereNotNull('logged_rule_ids')->count(),
        ];

        $startDate = now()->startOfDay()->subDays(6);
        $endDate = now()->endOfDay();

        $dailyRows = DB::table('traffic_logs')
            ->selectRaw('DATE(timestamp) AS period, decision, COUNT(*) AS total')
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->groupByRaw('DATE(timestamp), decision')
            ->orderBy('period')
            ->get();

        $dailyCounts = [];

        foreach ($dailyRows as $row) {
            $dailyCounts[$row->period][$row->decision] = (int) $row->total;
        }

        $labels = [];
        $allowData = [];
        $denyData = [];

        for ($day = $startDate->copy(); $day->lte($endDate); $day->addDay()) {
            $date = $day->toDateString();

            $labels[] = $day->format('d/m');
            $allowData[] = $dailyCounts[$date]['allow'] ?? 0;
            $denyData[] = $dailyCounts[$date]['deny'] ?? 0;
        }

        $trafficChart = [
            'labels' => $labels,
            'allow' => $allowData,
            'deny' => $denyData,
        ];

        $topRules = DB::table('traffic_logs')
            ->leftJoin('rules', 'traffic_logs.rule_id', '=', 'rules.id')
            ->select(
                'traffic_logs.rule_id',
                'traffic_logs.is_default',
                'rules.priority',
                'rules.description'
            )
            ->selectRaw('COUNT(traffic_logs.id) AS total')
            ->groupBy(
                'traffic_logs.rule_id',
                'traffic_logs.is_default',
                'rules.priority',
                'rules.description'
            )
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'stats',
            'trafficChart',
            'topRules'
        ));
    }
}
