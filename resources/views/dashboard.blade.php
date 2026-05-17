<?php

use App\Models\MailLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

$senderEmail = Auth::user()?->sender_email;
$demoMode = empty($senderEmail);

// Get filters
$filter = request('filter', '30days');
$search = request('search', '');
$startDate = Carbon::now();
$endDate = Carbon::now();

if ($filter === '30days') {
    $startDate = $startDate->subDays(30);
} else {
    $startDate = $startDate->subYears(10);
}

// Build query
$query = MailLog::query()
    ->when(!$demoMode, fn($q) => $q->where('sender', $senderEmail))
    ->whereBetween('mail_at', [$startDate, $endDate]);

// Search filter
if ($search) {
    $query->where(function($q) use ($search) {
        $q->where('recipient', 'ilike', "%{$search}%")
          ->orWhere('subject', 'ilike', "%{$search}%");
    });
}

// Get stats
$stats = (clone $query)->clone()
    ->selectRaw("
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'deferred' THEN 1 ELSE 0 END) as deferred,
        SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    ")
    ->first();

$total = (int) $stats->total;
$sent = (int) $stats->sent;
$successRate = $total > 0 ? round(($sent / $total) * 100, 1) : 0;

// Get daily data for chart
$dailyData = (clone $query)->clone()
    ->selectRaw("DATE(mail_at) as date, COUNT(*) as count")
    ->groupBy('date')
    ->orderBy('date')
    ->limit(30)
    ->get();

// Get status breakdown
$statusBreakdown = (clone $query)->clone()
    ->selectRaw('status, COUNT(*) as count')
    ->groupBy('status')
    ->get()
    ->pluck('count', 'status');

// Get logs with pagination
$logs = $query->orderBy('mail_at', 'desc')->paginate(25);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BulkMail Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f3f4f6; }

        .container { max-width: 90rem; margin: 0 auto; padding: 2rem 1rem; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        h1 { font-size: 1.5rem; font-weight: 600; color: #111827; }

        .controls { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
        .btn { background: #1f2937; color: white; padding: 0.5rem 1rem; border-radius: 0.25rem; text-decoration: none; font-size: 0.875rem; border: none; cursor: pointer; }
        .btn:hover { background: #374151; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        select, input { padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; font-size: 0.875rem; }
        input { width: 250px; }

        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .card { background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-label { font-size: 0.875rem; color: #6b7280; margin-bottom: 0.25rem; }
        .stat-number { font-size: 2rem; font-weight: 700; color: #111827; }
        .text-green { color: #16a34a; }
        .text-yellow { color: #ca8a04; }
        .text-red { color: #dc2626; }

        .charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        .chart-card { background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .chart-card h3 { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; }
        .bar-chart { display: flex; align-items: flex-end; gap: 2px; height: 150px; padding-top: 1rem; }
        .bar { background: #3b82f6; flex: 1; min-height: 2px; border-radius: 2px 2px 0 0; }
        .pie-chart { width: 150px; height: 150px; border-radius: 50%; background: conic-gradient(#22c55e 0% 64%, #eab308 64% 75%, #ef4444 75% 88%, #dc2626 88% 100%); margin: 0 auto; }
        .legend { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem; font-size: 0.75rem; }
        .legend-item { display: flex; align-items: center; gap: 0.25rem; }
        .legend-color { width: 12px; height: 12px; border-radius: 2px; }

        .table-card { background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .table-header h3 { font-size: 1.125rem; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 0.75rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb; font-size: 0.75rem; color: #6b7280; text-transform: uppercase; }
        td { padding: 0.75rem; border-bottom: 1px solid #e5e7eb; font-size: 0.875rem; }
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 500; }
        .badge-sent { background: #dcfce7; color: #166534; }
        .badge-deferred { background: #fef9c3; color: #854d0e; }
        .badge-bounced { background: #fee2e2; color: #991b1b; }
        .badge-failed { background: #fecaca; color: #7f1d1d; }
        .smtp-text { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .pagination { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; font-size: 0.875rem; color: #6b7280; }
        .pagination a { color: #3b82f6; text-decoration: none; }
        .pagination a:hover { text-decoration: underline; }

        .empty { text-align: center; padding: 2rem; color: #6b7280; }

        @media (max-width: 768px) {
            .grid { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
            .controls { flex-direction: column; align-items: stretch; }
            input { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>BulkMail Dashboard</h1>
            <div class="controls">
                <form method="get" style="display:inline;">
                    <select name="filter" onchange="this.form.submit()">
                        <option value="30days" {{ $filter === '30days' ? 'selected' : '' }}>Last 30 Days</option>
                        <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>All Time</option>
                    </select>
                </form>
                <a href="/dashboard" class="btn">Refresh</a>
                <a href="/profile" class="btn">Profile</a>
                <form method="post" action="/logout" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-danger">Logout</button>
                </form>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid">
            <div class="card">
                <div class="stat-label">Total Sent</div>
                <div class="stat-number">{{ number_format($total) }}</div>
            </div>
            <div class="card">
                <div class="stat-label">Success Rate</div>
                <div class="stat-number text-green">{{ $successRate }}%</div>
            </div>
            <div class="card">
                <div class="stat-label">Deferred</div>
                <div class="stat-number text-yellow">{{ number_format($stats->deferred) }}</div>
            </div>
            <div class="card">
                <div class="stat-label">Failed / Bounced</div>
                <div class="stat-number text-red">{{ number_format($stats->bounced + $stats->failed) }}</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3>Mail Volume Over Time</h3>
                <?php
                $maxCount = $dailyData->max('count') ?: 1;
                $totalDays = $dailyData->count();
                ?>
                <div class="bar-chart">
                    @foreach($dailyData as $day)
                    <?php $height = $maxCount > 0 ? round(($day->count / $maxCount) * 100) : 0; ?>
                    <div class="bar" style="height: {{ $height }}%; background: #3b82f6;" title="{{ $day->date }}: {{ $day->count }}"></div>
                    @endforeach
                </div>
                @if($dailyData->isEmpty())
                <div class="empty">No data for chart</div>
                @endif
            </div>
            <div class="chart-card">
                <h3>Status Breakdown</h3>
                <?php
                $sentPct = $total > 0 ? round(($statusBreakdown['sent'] ?? 0) / $total * 100) : 0;
                $deferredPct = $total > 0 ? round(($statusBreakdown['deferred'] ?? 0) / $total * 100) : 0;
                $bouncedPct = $total > 0 ? round(($statusBreakdown['bounced'] ?? 0) / $total * 100) : 0;
                $failedPct = $total > 0 ? round(($statusBreakdown['failed'] ?? 0) / $total * 100) : 0;
                ?>
                <div class="pie-chart"></div>
                <div class="legend">
                    <div class="legend-item"><div class="legend-color" style="background: #22c55e;"></div> Sent ({{ $sentPct }}%)</div>
                    <div class="legend-item"><div class="legend-color" style="background: #eab308;"></div> Deferred ({{ $deferredPct }}%)</div>
                    <div class="legend-item"><div class="legend-color" style="background: #ef4444;"></div> Bounced ({{ $bouncedPct }}%)</div>
                    <div class="legend-item"><div class="legend-color" style="background: #dc2626;"></div> Failed ({{ $failedPct }}%)</div>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="table-card">
            <div class="table-header">
                <h3>Recent Mail Logs</h3>
                <form method="get">
                    <input type="text" name="search" placeholder="Search recipient or subject..." value="{{ $search }}">
                    <button type="submit" class="btn">Search</button>
                    @if($search)
                    <a href="/dashboard?filter={{ $filter }}" class="btn" style="background: #6b7280;">Clear</a>
                    @endif
                </form>
            </div>

            @if($logs->isEmpty())
            <div class="empty">No data found</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Recipient</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>SMTP Response</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                        <tr>
                            <td>{{ $log->mail_at->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $log->recipient }}</td>
                            <td>{{ $log->subject ?: '-' }}</td>
                            <td>
                                <span class="badge badge-{{ $log->status }}">
                                    {{ $log->status }}
                                </span>
                            </td>
                            <td class="smtp-text" title="{{ $log->smtp_response }}">
                                {{ $log->smtp_response ?: '-' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="pagination">
                    <span>Showing {{ $logs->firstItem() }} - {{ $logs->lastItem() }} of {{ $logs->total() }} results</span>
                    <div>
                        @if($logs->previousPageUrl())
                        <a href="{{ $logs->previousPageUrl() }}&search={{ $search }}&filter={{ $filter }}">&laquo; Previous</a>
                        @endif
                        @if($logs->nextPageUrl())
                        <a href="{{ $logs->nextPageUrl() }}&search={{ $search }}&filter={{ $filter }}" style="margin-left: 1rem;">Next &raquo;</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</body>
</html>