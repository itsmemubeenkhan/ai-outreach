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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('business_name')->nullable();
            $table->unsignedInteger('number_of_employees')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('corporate_email')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('website')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('phone_type', 32)->nullable();
            $table->string('street_address')->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->string('state', 100)->nullable()->index();
            $table->string('city', 100)->nullable()->index();
            $table->string('country', 100)->nullable()->index();
            $table->string('category', 120)->nullable()->index();
            $table->string('source', 120)->nullable()->index();
            $table->string('email_status', 24)->default('unknown')->index();
            $table->string('lead_status', 24)->default('new')->index();
            $table->integer('lead_score')->default(0);
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
