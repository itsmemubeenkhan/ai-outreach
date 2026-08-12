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
        Schema::create('dialer_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category')->nullable()->index();
            $table->json('filters')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('current_lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->unsignedBigInteger('last_lead_id')->default(0);
            $table->unsignedInteger('calls_completed')->default(0);
            $table->unsignedSmallInteger('auto_next_delay')->default(5);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dialer_sessions');
    }
};
