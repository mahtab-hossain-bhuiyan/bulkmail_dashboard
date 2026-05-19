<?php

use App\Models\MailLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

$senderEmail = Auth::user()?->sender_email;
$domain = Auth::user()?->sender_email;

// Extract domain part for matching
$domainPart = $senderEmail ? substr(strrchr($senderEmail, '@'), 1) : null;

// Get filters
$filter = request('filter', 'all');
$statusFilter = request('status', 'all');
$search = request('search', '');
$startDate = request('start_date');
$endDate = request('end_date');

// Custom date range
if ($startDate && $endDate) {
    $startDate = Carbon::parse($startDate);
    $endDate = Carbon::parse($endDate);
    $filter = 'custom';
} elseif ($filter === 'today') {
    $startDate = Carbon::today();
    $endDate = Carbon::now();
} elseif ($filter === '7days') {
    $startDate = Carbon::now()->subDays(7);
    $endDate = Carbon::now();
} elseif ($filter === '30days') {
    $startDate = Carbon::now()->subDays(30);
    $endDate = Carbon::now();
} else {
    // "All Time" - don't filter by date, just use a very old start date
    $startDate = Carbon::create(2020, 1, 1);
    $endDate = null;
}

// Build query - match by domain part
$query = MailLog::query()
    ->when($domainPart, fn($q) => $q->where('sender', 'ilike', "%{$domainPart}%"))  // Match by domain
    ->when($startDate, fn($q) => $q->where('mail_at', '>=', $startDate))
    ->when($endDate, fn($q) => $q->where('mail_at', '<=', $endDate))
    ->when($statusFilter !== 'all' && $statusFilter, fn($q) => $q->where('status', $statusFilter));

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
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .header h1 { font-size: 1.5rem; }
        .nav-btn { background: #1f2937; color: white; padding: 0.5rem 1rem; border-radius: 0.25rem; text-decoration: none; font-size: 0.875rem; }
        .btn-admin { background: #7c3aed; }
        .btn-danger { background: #dc2626; }

        .controls { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
        .controls select, .controls input { padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; font-size: 0.875rem; }
        .btn { background: #1f2937; color: white; padding: 0.5rem 1rem; border-radius: 0.25rem; border: none; font-size: 0.875rem; cursor: pointer; }
        .btn:hover { background: #374151; }

        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .card { background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-label { font-size: 0.875rem; color: #6b7280; margin-bottom: 0.25rem; }
        .stat-number { font-size: 2rem; font-weight: 700; color: #111827; }
        .text-green { color: #16a34a; }
        .text-yellow { color: #ca8a04; }
        .text-red { color: #dc2626; }

        .table-card { background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem; }
        .table-header h3 { font-size: 1.125rem; font-weight: 600; }

        table { width: 100%; border-collapse: collapse; overflow-x: auto; display: block; }
        th { text-align: left; padding: 0.75rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb; font-size: 0.75rem; color: #6b7280; text-transform: uppercase; white-space: nowrap; }
        td { padding: 0.75rem; border-bottom: 1px solid #e5e7eb; font-size: 0.875rem; white-space: nowrap; }

        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 500; }
        .badge-sent { background: #dcfce7; color: #166534; }
        .badge-deferred { background: #fef9c3; color: #854d0e; }
        .badge-bounced { background: #fee2e2; color: #991b1b; }
        .badge-failed { background: #fecaca; color: #7f1d1d; }

        .pagination { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; font-size: 0.875rem; color: #6b7280; flex-wrap: wrap; gap: 1rem; }
        .pagination a { color: #3b82f6; text-decoration: none; }
        .pagination a:hover { text-decoration: underline; }

        @media (max-width: 768px) {
            .grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>BulkMail Dashboard</h1>
            <div class="controls">
                @if(Auth::user()?->is_admin)
                <a href="/admin" class="nav-btn btn-admin">Admin Panel</a>
                @endif
                <a href="/dashboard?filter=all" class="btn">Refresh</a>
                <a href="/profile" class="nav-btn">Profile</a>
                <form method="post" action="/logout" style="display:inline;">
                    @csrf
                    <button type="submit" class="nav-btn btn-danger">Logout</button>
                </form>
            </div>
        </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom:1.5rem;">
            <form method="get" class="controls">
                <select name="filter">
                    <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>All Time</option>
                    <option value="today" {{ $filter === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="7days" {{ $filter === '7days' ? 'selected' : '' }}>Last 7 Days</option>
                    <option value="30days" {{ $filter === '30days' ? 'selected' : '' }}>Last 30 Days</option>
                    <option value="custom">Custom Range</option>
                </select>
                <select name="status">
                    <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All Status</option>
                    <option value="sent" {{ $statusFilter === 'sent' ? 'selected' : '' }}>Sent</option>
                    <option value="deferred" {{ $statusFilter === 'deferred' ? 'selected' : '' }}>Deferred</option>
                    <option value="bounced" {{ $statusFilter === 'bounced' ? 'selected' : '' }}>Bounced</option>
                    <option value="failed" {{ $statusFilter === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
                <input type="date" name="start_date" value="{{ request('start_date') }}">
                <input type="date" name="end_date" value="{{ request('end_date') }}">
                <input type="text" name="search" placeholder="Search recipient..." value="{{ $search }}">
                <button type="submit" class="btn">Apply Filters</button>
            </form>
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

        <!-- Data Table -->
        <div class="table-card">
            <div class="table-header">
                <h3>Recent Mail Logs ({{ number_format($logs->total()) }} total)</h3>
            </div>

            @if($logs->isEmpty())
            <div style="text-align:center;padding:2rem;color:#6b7280;">No data found</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Queue ID</th>
                            <th>Recipient</th>
                            <th>Status</th>
                            <th>SMTP Response</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                        <tr>
                            <td>{{ $log->mail_at->format('Y-m-d H:i:s') }}</td>
                            <td style="font-family:monospace;font-size:0.75rem;">{{ $log->message_id ?: '-' }}</td>
                            <td>{{ $log->recipient }}</td>
                            <td>
                                <span class="badge badge-{{ $log->status }}">
                                    {{ $log->status }}
                                </span>
                            </td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;">
                                {{ Str::limit($log->smtp_response, 50) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="pagination">
                    <span>Showing {{ $logs->firstItem() }} - {{ $logs->lastItem() }}</span>
                    <div>
                        @if($logs->previousPageUrl())
                        <a href="{{ $logs->previousPageUrl() }}&filter={{ $filter }}&status={{ $statusFilter }}&search={{ $search }}&start_date={{ request('start_date') }}&end_date={{ request('end_date') }}">&laquo; Previous</a>
                        @endif
                        @if($logs->nextPageUrl())
                        <a href="{{ $logs->nextPageUrl() }}&filter={{ $filter }}&status={{ $statusFilter }}&search={{ $search }}&start_date={{ request('start_date') }}&end_date={{ request('end_date') }}">Next &raquo;</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</body>
</html>