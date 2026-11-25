<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\LogQueryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LogController extends Controller
{
    public function decisions(
        Request $request,
        LogQueryService $logQueryService
    ): View {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'ip' => ['nullable', 'ip'],
            'decision' => ['nullable', Rule::in(['allow', 'deny'])],
        ]);

        return view('logs.decisions', [
            'logs' => $logQueryService->decisions($filters),
        ]);
    }

    public function audits(
        Request $request,
        LogQueryService $logQueryService
    ): View {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'action' => ['nullable', Rule::in(AuditService::ALL)],
            'actor' => ['nullable', 'string', 'max:100'],
        ]);

        return view('logs.audits', [
            'logs' => $logQueryService->audits($filters),
            'actions' => AuditService::ALL,
        ]);
    }
}
