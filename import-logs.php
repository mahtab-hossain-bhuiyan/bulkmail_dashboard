#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$logFiles = [
    '/var/log/bulkmail/maillog_17.log',
    '/var/log/bulkmail/maillog_18.log',
];

foreach ($logFiles as $logFile) {
    if (!file_exists($logFile)) {
        echo "File not found: $logFile\n";
        continue;
    }

    echo "Processing: $logFile\n";
    $handle = fopen($logFile, 'r');
    $count = 0;
    $errors = [];

    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if (empty($line)) continue;

        // Only process lines with status=
        if (!preg_match('/status=(\w+)/', $line, $statusMatch)) {
            continue;
        }

        $status = $statusMatch[1];

        // Parse timestamp
        $timestamp = null;
        if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})\+06:00/', $line, $m)) {
            try {
                $ts = str_replace('T', ' ', $m[1]);
                $timestamp = Carbon::parse($ts);
            } catch (Exception $e) {
                $timestamp = Carbon::now();
            }
        }

        // Extract recipient
        $recipient = '';
        if (preg_match('/to=<([^>]+)>/', $line, $m)) {
            $recipient = $m[1];
        }

        // Extract message_id
        $messageId = '';
        if (preg_match('/postfix[^:]*:([A-F0-9]+):/', $line, $m)) {
            $messageId = $m[1];
        }

        // Extract SMTP response
        $smtpResponse = '';
        if (preg_match('/\(([^)]+)\)\s*$/', $line, $m)) {
            $smtpResponse = $m[1];
        }

        if (!$recipient) {
            continue;
        }

        try {
            DB::table('mail_logs')->insert([
                'sender' => 'noreply@lmmnew3.com',
                'recipient' => $recipient,
                'subject' => null,
                'message_id' => $messageId ?: null,
                'status' => $status,
                'smtp_response' => substr($smtpResponse, 0, 500),
                'mail_at' => $timestamp ?? Carbon::now(),
                'domain' => 'lmmnew3',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            $count++;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
            if (count($errors) < 5) {
                echo "Error: " . $e->getMessage() . "\n";
            }
        }
    }

    fclose($handle);
    echo "Imported $count records from $logFile\n";
}

echo "\nTotal in DB: " . DB::table('mail_logs')->count() . "\n";

// Show sample
$sample = DB::table('mail_logs')->take(5)->get();
foreach ($sample as $row) {
    echo " - $row->recipient: $row->status\n";
}