<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Update domains table
        Schema::table('domains', function (Blueprint $table) {
            $table->string('sender_email', 255)->nullable()->change();
            $table->string('full_name', 255)->nullable()->after('name');
            $table->boolean('is_active')->default(true)->change();
        });

        // Clear old data and add proper domains from client list
    }

    public function down(): void
    {
        // No rollback needed
    }
};