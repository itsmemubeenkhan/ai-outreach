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
        Schema::create('campaign_sequence_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->longText('body');
            $table->unsignedInteger('delay_days')->default(0);
            $table->unsignedInteger('position');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['campaign_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_sequence_steps');
    }
};
