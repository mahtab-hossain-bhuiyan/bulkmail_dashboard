#!/usr/bin/env php
<?php
/**
 * Optimized BulkMail Log Sync
 * - Uses batch inserts for performance
 * - Properly extracts sender from log lines
 * - Dynamically syncs last 3 days
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Configuration
$remoteHost = '203.76.100.58';
$remotePort = 6622;
$remoteUser = 'system';
$remotePass = 'Link3@n0c';
$logBase = '/data/logs/mail';

// Directories to sync
$directories = ['emailx', 'lmm.link3.net', 'lmmnew', 'lmmnew3', 'lmmnew6'];

// Get current year and month (Bangladesh timezone)
$currentYear = Carbon::now('Asia/Dhaka')->format('Y');
$currentMonth = Carbon::now('Asia/Dhaka')->format('m');

// Generate last 3 days dynamically
$days = [];
for ($i = 0; $i < 3; $i++) {
    $days[] = Carbon::now('Asia/Dhaka')->subDays($i)->format('d');
}

echo "=== BulkMail Log Sync Started ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Sync: Year=$currentYear, Month=$currentMonth, Days=" . implode(',', $days) . "\n\n";

$totalImported = 0;

foreach ($directories as $dir) {
    echo "Processing: $dir\n";
    $dirImported = 0;

    // First pass: collect sender mappings from queue manager lines
    $senderMap = [];
    $mailRecords = [];

    foreach ($days as $day) {
        $remotePath = "$logBase/$dir/$currentYear/$currentMonth/$day/maillog";
        $cmd = "sshpass -p '$remotePass' ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 -p $remotePort $remoteUser@$remoteHost 'cat $remotePath 2>/dev/null'";
        $output = shell_exec($cmd);

        if (empty($output)) {
            continue;
        }

        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Extract queue ID
            $queueId = '';
            if (preg_match('/^.*?\s+(\w+):\s+(from=<|to=<)/', $line, $m)) {
                $queueId = $m[1];
            }

            // Extract sender from "from=<email>" lines
            if (preg_match('/from=<([^>]+)>/', $line, $m)) {
                $senderMap[$queueId] = $m[1];
            }

            // Extract recipient and status
            if (preg_match('/status=(\w+)/', $line) && preg_match('/to=<([^>]+)>/', $line, $recipientMatch)) {
                $timestamp = null;
                if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})\+06:00/', $line, $m)) {
                    try {
                        $timestamp = Carbon::parse(str_replace('T', ' ', $m[1]));
                    } catch (Exception $e) {
                        $timestamp = Carbon::now();
                    }
                }

                $recipient = $recipientMatch[1];
                $status = preg_match('/status=(\w+)/', $line, $statusMatch) ? $statusMatch[1] : 'unknown';

                $messageId = '';
                if (preg_match('/postfix[^:]*:([A-F0-9]+):/', $line, $m)) {
                    $messageId = $m[1];
                }

                $smtpResponse = '';
                if (preg_match('/\(([^)]+)\)\s*$/', $line, $m)) {
                    $smtpResponse = substr($m[1], 0, 500);
                }

                // Get sender from map, or use default
                $sender = isset($senderMap[$queueId]) ? $senderMap[$queueId] : "noreply@$dir.local";

                $mailRecords[] = [
                    'sender' => $sender,
                    'recipient' => $recipient,
                    'subject' => null,
                    'message_id' => $messageId ?: null,
                    'status' => $status,
                    'smtp_response' => $smtpResponse,
                    'mail_at' => $timestamp ?: Carbon::now(),
                    'domain' => $dir,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];

                // Batch insert
                if (count($mailRecords) >= 500) {
                    try {
                        DB::table('mail_logs')->insert($mailRecords);
                        $totalImported += count($mailRecords);
                        $dirImported += count($mailRecords);
                    } catch (Exception $e) {
                        // Skip duplicates/errors
                    }
                    $mailRecords = [];
                }
            }
        }
    }

    // Insert remaining
    if (!empty($mailRecords)) {
        try {
            DB::table('mail_logs')->insert($mailRecords);
            $totalImported += count($mailRecords);
            $dirImported += count($mailRecords);
        } catch (Exception $e) {
            // Skip
        }
    }

    echo "  - $dir: $dirImported records\n";
}

echo "\n=== Sync Complete ===\n";
echo "Total imported: $totalImported\n";
echo "Total in database: " . DB::table('mail_logs')->count() . "\n";