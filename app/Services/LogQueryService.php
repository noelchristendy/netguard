<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\TrafficLog;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class LogQueryService
{
    private const PER_PAGE = 25;

    public function decisions(array $filters): LengthAwarePaginator
    {
        $query = TrafficLog::query()
            ->with([
                'rule:id,priority,action,description',
            ]);

        $this->applyDateFilters($query, $filters);

        $ip = trim((string) ($filters['ip'] ?? ''));

        if ($ip !== '') {
            $query->where(function (Builder $q) use ($ip) {
                $q->where('src_ip', $ip)
                    ->orWhere('dst_ip', $ip);
            });
        }

        $decision = strtolower(trim((string) ($filters['decision'] ?? '')));

        if (in_array($decision, ['allow', 'deny'], true)) {
            $query->where('decision', $decision);
        }

        return $query
            ->orderByDesc('timestamp')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    public function audits(array $filters): LengthAwarePaginator
    {
        $query = AuditLog::query()
            ->with([
                'user:id,username,name',
            ]);

        $this->applyDateFilters($query, $filters);

        $action = trim((string) ($filters['action'] ?? ''));

        if (in_array($action, AuditService::ALL, true)) {
            $query->where('action', $action);
        }

        $userId = $filters['user_id'] ?? null;

        if (is_numeric($userId) && (int) $userId > 0) {
            $query->where('user_id', (int) $userId);
        }

        $actor = trim((string) ($filters['actor'] ?? ''));

        if ($actor !== '') {
            $query->whereHas('user', function (Builder $q) use ($actor) {
                $q->where('username', 'like', "%{$actor}%")
                    ->orWhere('name', 'like', "%{$actor}%");
            });
        }

        return $query
            ->orderByDesc('timestamp')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    private function applyDateFilters(Builder $query, array $filters): void
    {
        $dateFrom = $this->parseDate($filters['date_from'] ?? null, false);

        $dateTo = $this->parseDate($filters['date_to'] ?? null, true);

        if ($dateFrom !== null) {
            $query->where('timestamp', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('timestamp', '<=', $dateTo);
        }
    }

    private function parseDate(mixed $value, bool $endOfDay): ?Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value, config('app.timezone'));

            return $endOfDay
                ? $date->endOfDay()
                : $date->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
