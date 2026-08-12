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
        Schema::create('import_rejections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('row_number');
            $table->text('reason');
            $table->json('row_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_rejections');
    }
};
