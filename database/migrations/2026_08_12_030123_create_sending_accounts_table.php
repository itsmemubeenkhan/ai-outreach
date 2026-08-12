<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sending_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sender_name');
            $table->string('email')->index();
            $table->string('smtp_host');
            $table->unsignedSmallInteger('smtp_port');
            $table->string('smtp_username');
            $table->text('smtp_password');
            $table->string('smtp_encryption', 16)->default('tls');
            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->nullable();
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable();
            $table->string('imap_encryption', 16)->nullable();
            $table->unsignedInteger('daily_limit')->default(50);
            $table->unsignedInteger('sent_today')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('last_reset_at')->nullable();
            $table->string('status', 16)->default('paused')->index();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sending_accounts');
    }
};
