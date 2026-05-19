<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\MailLog;
use Carbon\Carbon;

class BulkAdminController extends Controller
{

    public function index()
    {
        $domains = DB::table('domains')->orderBy('full_name')->get();
        $users = DB::table('users')
            ->select('users.*', 'domains.full_name as domain_name')
            ->leftJoin('domains', 'users.sender_email', '=', 'domains.sender_email')
            ->orderBy('users.name')
            ->get();

        return view('admin.index', compact('domains', 'users'));
    }

    public function mailLogs(Request $request)
    {
        $domains = DB::table('domains')->orderBy('full_name')->get();

        // Get sender statistics - grouped by domain
        $senderStats = MailLog::selectRaw('sender, COUNT(*) as total,
            SUM(CASE WHEN status = \'sent\' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = \'deferred\' THEN 1 ELSE 0 END) as deferred,
            SUM(CASE WHEN status = \'bounced\' THEN 1 ELSE 0 END) as bounced,
            SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed')
            ->groupBy('sender')
            ->orderByDesc('total')
            ->limit(50)
            ->get();

        $query = MailLog::query();

        // Filter by domain (match by domain part, not exact)
        $domainFilter = $request->get('domain');
        if ($domainFilter && $domainFilter !== 'all') {
            $domainPart = substr(strrchr($domainFilter, '@'), 1);
            $query->where('sender', 'ilike', "%{$domainPart}%");
        }

        // Filter by status
        $statusFilter = $request->get('status', 'all');
        if ($statusFilter && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        // Filter by recipient (to address)
        $recipientFilter = $request->get('recipient');
        if ($recipientFilter) {
            $query->where('recipient', 'ilike', "%{$recipientFilter}%");
        }

        // Filter by sender search
        $senderFilter = $request->get('sender_filter');
        if ($senderFilter) {
            $query->where('sender', 'ilike', "%{$senderFilter}%");
        }

        // Date range
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        if ($startDate) {
            $query->where('mail_at', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $query->where('mail_at', '<=', Carbon::parse($endDate)->endOfDay());
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

        // Get logs with pagination
        $logs = $query->orderBy('mail_at', 'desc')->paginate(50);

        return view('admin.mail-logs', compact('domains', 'logs', 'stats', 'senderStats', 'domainFilter', 'statusFilter', 'recipientFilter', 'startDate', 'endDate'));
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'domain_id' => 'required|exists:domains,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $domain = DB::table('domains')->find($request->domain_id);

        DB::table('users')->insert([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'sender_email' => $domain->sender_email,
            'company_name' => $domain->full_name,
            'is_admin' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'User created for ' . $domain->full_name);
    }

    public function deleteUser($id)
    {
        $user = DB::table('users')->find($id);
        if ($user && !$user->is_admin) {
            DB::table('users')->delete($id);
            return back()->with('success', 'User deleted');
        }
        return back()->with('error', 'Cannot delete admin user');
    }

    public function toggleDomain($id)
    {
        $domain = DB::table('domains')->find($id);
        if ($domain) {
            DB::table('domains')->where('id', $id)->update([
                'is_active' => !$domain->is_active,
                'updated_at' => now(),
            ]);
        }
        return back();
    }
}