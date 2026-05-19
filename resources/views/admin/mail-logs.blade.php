<?php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$user = Auth::user();
$domains = DB::table('domains')->orderBy('full_name')->get();

$filter = request('domain', 'all');
$statusFilter = request('status', 'all');
$recipientFilter = request('recipient', '');
$startDate = request('start_date', '');
$endDate = request('end_date', '');
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mail Logs - Admin Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f3f4f6; }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 1rem; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .header h1 { font-size: 1.5rem; }
        .nav-btn { background: #1f2937; color: white; padding: 0.5rem 1rem; border-radius: 0.25rem; text-decoration: none; font-size: 0.875rem; }
        .btn-danger { background: #dc2626; }
        .btn-admin { background: #7c3aed; }

        .card { background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
        .card h2 { font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem; }
        .form-group input, .form-group select {
            width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; font-size: 1rem;
        }
        .btn { background: #1f2937; color: white; padding: 0.5rem 1rem; border-radius: 0.25rem; border: none; font-size: 0.875rem; cursor: pointer; }
        .btn:hover { background: #374151; }

        table { width: 100%; border-collapse: collapse; overflow-x: auto; display: block; }
        th { text-align: left; padding: 0.75rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb; font-size: 0.75rem; color: #6b7280; text-transform: uppercase; white-space: nowrap; }
        td { padding: 0.75rem; border-bottom: 1px solid #e5e7eb; font-size: 0.875rem; white-space: nowrap; }
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; }
        .badge-sent { background: #dcfce7; color: #166534; }
        .badge-deferred { background: #fef9c3; color: #854d0e; }
        .badge-bounced { background: #fee2e2; color: #991b1b; }
        .badge-failed { background: #fecaca; color: #7f1d1d; }

        .grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-label { font-size: 0.875rem; color: #6b7280; margin-bottom: 0.25rem; }
        .stat-number { font-size: 1.5rem; font-weight: 700; color: #111827; }
        .text-green { color: #16a34a; }
        .text-yellow { color: #ca8a04; }
        .text-red { color: #dc2626; }

        .controls { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
        .controls select, .controls input { padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; font-size: 0.875rem; }

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
            <h1>Mail Logs - Admin View</h1>
            <div class="controls">
                <a href="/admin" class="nav-btn">User Management</a>
                <a href="/dashboard" class="nav-btn">Dashboard</a>
                <form method="post" action="/logout" style="display:inline;">
                    @csrf
                    <button type="submit" class="nav-btn btn-danger">Logout</button>
                </form>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid">
            <div class="stat-card">
                <div class="stat-label">Total Sent</div>
                <div class="stat-number">{{ number_format($stats->total) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Delivered</div>
                <div class="stat-number text-green">{{ number_format($stats->sent) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Deferred</div>
                <div class="stat-number text-yellow">{{ number_format($stats->deferred) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Bounced</div>
                <div class="stat-number text-red">{{ number_format($stats->bounced) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Failed</div>
                <div class="stat-number text-red">{{ number_format($stats->failed) }}</div>
            </div>
        </div>

        <!-- Sender Stats Summary -->
        <div class="card">
            <h2>Sender Summary (Top 50)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Sender Email</th>
                        <th>Total</th>
                        <th>Delivered</th>
                        <th>Deferred</th>
                        <th>Bounced</th>
                        <th>Failed</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($senderStats as $stat)
                    <tr>
                        <td style="font-weight:500;">{{ $stat->sender }}</td>
                        <td><strong>{{ number_format($stat->total) }}</strong></td>
                        <td class="text-green">{{ number_format($stat->sent) }}</td>
                        <td class="text-yellow">{{ number_format($stat->deferred) }}</td>
                        <td class="text-red">{{ number_format($stat->bounced) }}</td>
                        <td class="text-red">{{ number_format($stat->failed) }}</td>
                        <td>
                            <a href="/admin/mail-logs?domain={{ $stat->sender }}" class="btn" style="font-size:0.75rem;padding:0.25rem 0.5rem;">View Logs</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Filters -->
        <div class="card">
            <form method="get" class="controls">
                <select name="domain">
                    <option value="all">All Senders</option>
                    @foreach($domains as $d)
                    <option value="{{ $d->sender_email }}" {{ $domainFilter === $d->sender_email ? 'selected' : '' }}>{{ $d->full_name }} ({{ $d->sender_email }})</option>
                    @endforeach
                </select>
                <select name="status">
                    <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All Status</option>
                    <option value="sent" {{ $statusFilter === 'sent' ? 'selected' : '' }}>Sent</option>
                    <option value="deferred" {{ $statusFilter === 'deferred' ? 'selected' : '' }}>Deferred</option>
                    <option value="bounced" {{ $statusFilter === 'bounced' ? 'selected' : '' }}>Bounced</option>
                    <option value="failed" {{ $statusFilter === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
                <input type="text" name="recipient" placeholder="Search recipient..." value="{{ $recipientFilter }}">
                <input type="text" name="sender_filter" placeholder="Search sender..." value="{{ request('sender_filter') }}">
                <input type="date" name="start_date" value="{{ $startDate }}">
                <input type="date" name="end_date" value="{{ $endDate }}">
                <button type="submit" class="btn">Apply Filters</button>
                <a href="/admin/mail-logs" class="btn" style="background:#6b7280;	text-decoration:none;">Reset</a>
            </form>
        </div>

        <!-- Mail Logs Table -->
        <div class="card">
            <h2>Recent Mail Logs ({{ number_format($logs->total()) }} total)</h2>

            @if($logs->isEmpty())
            <div style="text-align:center;padding:2rem;color:#6b7280;">No data found</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>From (Domain)</th>
                            <th>To (Recipient)</th>
                            <th>Status</th>
                            <th>SMTP Response</th>
                            <th>Queue ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                        <tr>
                            <td>{{ $log->mail_at->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $log->sender }}</td>
                            <td>{{ $log->recipient }}</td>
                            <td>
                                <span class="badge badge-{{ $log->status }}">
                                    {{ $log->status }}
                                </span>
                            </td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;">
                                {{ Str::limit($log->smtp_response, 50) }}
                            </td>
                            <td style="font-family:monospace;font-size:0.75rem;">{{ $log->message_id ?: '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="pagination">
                    <span>Showing {{ $logs->firstItem() }} - {{ $logs->lastItem() }}</span>
                    <div>
                        @if($logs->previousPageUrl())
                        <a href="{{ $logs->previousPageUrl() }}&domain={{ $domainFilter }}&status={{ $statusFilter }}&recipient={{ $recipientFilter }}&start_date={{ $startDate }}&end_date={{ $endDate }}">&laquo; Previous</a>
                        @endif
                        @if($logs->nextPageUrl())
                        <a href="{{ $logs->nextPageUrl() }}&domain={{ $domainFilter }}&status={{ $statusFilter }}&recipient={{ $recipientFilter }}&start_date={{ $startDate }}&end_date={{ $endDate }}">Next &raquo;</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</body>
</html>