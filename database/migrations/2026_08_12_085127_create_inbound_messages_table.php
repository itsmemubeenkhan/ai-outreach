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
        Schema::create('inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sending_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('outbound_email_id')->nullable()->constrained()->nullOnDelete();
            $table->string('internet_message_id');
            $table->string('in_reply_to')->nullable()->index();
            $table->text('references_header')->nullable();
            $table->string('from_email')->index();
            $table->string('from_name')->nullable();
            $table->string('to_email');
            $table->string('subject')->nullable();
            $table->longText('body_text');
            $table->longText('body_html')->nullable();
            $table->timestamp('received_at')->index();
            $table->string('classification', 32)->nullable()->index();
            $table->decimal('classification_confidence', 4, 3)->nullable();
            $table->text('recommended_next_action')->nullable();
            $table->boolean('requires_human_action')->default(false)->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('ai_processed_at')->nullable();
            $table->date('suggested_follow_up_date')->nullable();
            $table->json('raw_metadata')->nullable();
            $table->timestamps();
            $table->unique(['sending_account_id', 'internet_message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbound_messages');
    }
};
