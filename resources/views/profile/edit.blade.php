<?php
use Illuminate\Support\Facades\Auth;
$user = Auth::user();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile - BulkMail</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .header h1 { font-size: 1.5rem; }
        .nav-btn {
            background: #1f2937;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            text-decoration: none;
            font-size: 0.875rem;
        }
        .btn-danger { background: #dc2626; }

        .card { background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
        .card h2 { font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem; }
        .form-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.25rem;
            font-size: 1rem;
        }
        .form-group input:focus { outline: none; border-color: #3b82f6; }

        .btn {
            background: #1f2937;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            border: none;
            font-size: 0.875rem;
            cursor: pointer;
        }
        .btn:hover { background: #374151; }

        .info { background: #eff6ff; padding: 1rem; border-radius: 0.25rem; margin-bottom: 1rem; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Profile Settings</h1>
            <div>
                <a href="/dashboard" class="nav-btn">Dashboard</a>
                <form method="post" action="/logout" style="display:inline;">
                    @csrf
                    <button type="submit" class="nav-btn btn-danger">Logout</button>
                </form>
            </div>
        </div>

        <div class="info">
            Logged in as: <strong><?php echo htmlspecialchars($user->email); ?></strong>
        </div>

        <form method="post" action="/profile">
            @csrf
            @method('patch')

            <div class="card">
                <h2>Update Profile</h2>
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" value="{{ $user->name }}" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ $user->email }}" required>
                </div>
                <div class="form-group">
                    <label>Sender Email (for filtering mail logs)</label>
                    <input type="email" name="sender_email" value="{{ $user->sender_email ?? '' }}" placeholder="e.g., noreply@yourdomain.com">
                    <small style="color: #6b7280; font-size: 0.75rem;">Leave empty to see all data</small>
                </div>
                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" name="company_name" value="{{ $user->company_name ?? '' }}">
                </div>
                <button type="submit" class="btn">Save Changes</button>
            </div>
        </form>
    </div>
</body>
</html>