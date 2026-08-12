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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('category')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedInteger('daily_limit')->default(50);
            $table->date('start_date')->nullable()->index();
            $table->string('sender_strategy', 32)->default('round_robin');
            $table->json('audience_filters')->nullable();
            $table->string('audience_status', 24)->default('pending')->index();
            $table->unsignedBigInteger('audience_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
