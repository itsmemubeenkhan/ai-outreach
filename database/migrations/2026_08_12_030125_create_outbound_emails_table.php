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
        Schema::create('outbound_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_sequence_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sending_account_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('message_uuid')->unique();
            $table->string('message_id')->nullable()->index();
            $table->string('subject');
            $table->string('recipient_email')->index();
            $table->string('status', 24)->default('queued')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->unique(['campaign_lead_id', 'campaign_sequence_step_id'], 'outbound_campaign_step_unique');
            $table->index(['campaign_id', 'status', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbound_emails');
    }
};
