<?php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$user = Auth::user();
$domains = DB::table('domains')->orderBy('full_name')->get();
$users = DB::table('users')
    ->select('users.*', 'domains.full_name as domain_name')
    ->leftJoin('domains', 'users.sender_email', '=', 'domains.sender_email')
    ->orderBy('users.name')
    ->get();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Panel - BulkMail</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f3f4f6; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .header h1 { font-size: 1.5rem; }
        .nav-btn { background: #1f2937; color: white; padding: 0.5rem 1rem; border-radius: 0.25rem; text-decoration: none; font-size: 0.875rem; }
        .btn-danger { background: #dc2626; }

        .card { background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
        .card h2 { font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem; }
        .form-group input, .form-group select {
            width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; font-size: 1rem;
        }
        .btn { background: #1f2937; color: white; padding: 0.5rem 1rem; border-radius: 0.25rem; border: none; font-size: 0.875rem; cursor: pointer; }
        .btn:hover { background: #374151; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 0.75rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb; font-size: 0.75rem; color: #6b7280; text-transform: uppercase; }
        td { padding: 0.75rem; border-bottom: 1px solid #e5e7eb; font-size: 0.875rem; }
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }

        .alert { padding: 1rem; border-radius: 0.25rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Panel</h1>
            <div>
                <a href="/admin/mail-logs" class="nav-btn btn-admin">View Mail Logs</a>
                <a href="/dashboard" class="nav-btn">Dashboard</a>
                <form method="post" action="/logout" style="display:inline;">
                    @csrf
                    <button type="submit" class="nav-btn btn-danger">Logout</button>
                </form>
            </div>
        </div>

        @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <!-- Create User Form -->
        <div class="card">
            <h2>Create New User</h2>
            <form method="post" action="/admin/user">
                @csrf
                <div class="form-group">
                    <label>Domain / Client</label>
                    <select name="domain_id" required>
                        <option value="">Select Domain</option>
                        @foreach($domains as $d)
                        <option value="{{ $d->id }}">{{ $d->full_name }} ({{ $d->sender_email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required minlength="6">
                </div>
                <button type="submit" class="btn">Create User</button>
            </form>
        </div>

        <!-- List of Users -->
        <div class="card">
            <h2>Users ({{ $users->count() }})</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Domain</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $u)
                    <tr>
                        <td>{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ $u->domain_name ?: '-' }}</td>
                        <td>
                            @if(!$u->is_admin)
                            <form method="post" action="/admin/user/{{ $u->id }}" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn" style="background:#dc2626;">Delete</button>
                            </form>
                            @else
                            <span style="color:#6b7280;">Admin</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- List of Domains -->
        <div class="card">
            <h2>Domains ({{ $domains->count() }})</h2>
            <table>
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Sender Email</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($domains as $d)
                    <tr>
                        <td>{{ $d->full_name }}</td>
                        <td>{{ $d->sender_email }}</td>
                        <td>
                            <span class="badge {{ $d->is_active ? 'badge-active' : 'badge-inactive' }}">
                                {{ $d->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <a href="/admin/domain/{{ $d->id }}/toggle" class="btn">
                                {{ $d->is_active ? 'Disable' : 'Enable' }}
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>