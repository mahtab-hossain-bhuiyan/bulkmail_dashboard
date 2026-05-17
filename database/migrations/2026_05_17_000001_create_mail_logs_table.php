<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sender', 255)->index();
            $table->string('recipient', 255)->index();
            $table->string('subject', 500)->nullable();
            $table->string('message_id', 255)->nullable()->unique();
            $table->enum('status', ['sent', 'deferred', 'bounced', 'failed'])->default('sent')->index();
            $table->text('smtp_response')->nullable();
            $table->timestamp('mail_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};