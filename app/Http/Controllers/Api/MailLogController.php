<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MailLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailLogController extends Controller
{
    /**
     * Get mail logs with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|string|in:sent,deferred,bounced,failed,all',
            'search' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Demo mode: show all data unless sender_email is explicitly set
        $senderEmail = $request->user()?->sender_email;
        $demoMode = empty($senderEmail);

        $query = MailLog::query()
            ->when(!$demoMode, fn($q) => $q->where('sender', $senderEmail))  // Exact match
            ->when($request->start_date, fn($q) => $q->dateRange($request->start_date, $request->end_date))
            ->when($request->status && $request->status !== 'all', fn($q) => $q->status($request->status))
            ->when($request->search, fn($q, $search) => $q->where(function($query) use ($search) {
                $query->where('recipient', 'ilike', "%{$search}%")
                      ->orWhere('subject', 'ilike', "%{$search}%");
            }))
            ->orderBy('mail_at', 'desc');

        $perPage = $request->per_page ?? 25;
        $logs = $query->paginate($perPage);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get aggregated statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $senderEmail = $request->user()?->sender_email;
        $demoMode = empty($senderEmail);

        $query = MailLog::query()
            ->when(!$demoMode, fn($q) => $q->where('sender', $senderEmail))  // Exact match
            ->when($request->start_date, fn($q) => $q->dateRange($request->start_date, $request->end_date));

        $stats = $query->clone()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = \'sent\' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = \'deferred\' THEN 1 ELSE 0 END) as deferred,
                SUM(CASE WHEN status = \'bounced\' THEN 1 ELSE 0 END) as bounced,
                SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed
            ')
            ->first();

        $total = (int) $stats->total;
        $sent = (int) $stats->sent;

        return response()->json([
            'total' => $total,
            'sent' => $sent,
            'deferred' => (int) $stats->deferred,
            'bounced' => (int) $stats->bounced,
            'failed' => (int) $stats->failed,
            'success_rate' => $total > 0 ? round(($sent / $total) * 100, 2) : 0,
        ]);
    }

    /**
     * Get chart data for visualizations
     */
    public function chartData(Request $request): JsonResponse
    {
        $senderEmail = $request->user()?->sender_email;
        $demoMode = empty($senderEmail);

        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)
            : Carbon::now()->subDays(30);
        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)
            : Carbon::now();

        $query = MailLog::query()
            ->when(!$demoMode, fn($q) => $q->where('sender', $senderEmail))  // Exact match
            ->whereBetween('mail_at', [$startDate, $endDate]);

        // Daily breakdown
        $dailyData = $query->clone()
            ->selectRaw('
                DATE(mail_at) as date,
                SUM(CASE WHEN status = \'sent\' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = \'deferred\' THEN 1 ELSE 0 END) as deferred,
                SUM(CASE WHEN status = \'bounced\' THEN 1 ELSE 0 END) as bounced,
                SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed,
                COUNT(*) as total
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Status breakdown
        $statusBreakdown = $query->clone()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        return response()->json([
            'timeline' => $dailyData,
            'status_breakdown' => [
                'sent' => $statusBreakdown['sent'] ?? 0,
                'deferred' => $statusBreakdown['deferred'] ?? 0,
                'bounced' => $statusBreakdown['bounced'] ?? 0,
                'failed' => $statusBreakdown['failed'] ?? 0,
            ],
        ]);
    }
}