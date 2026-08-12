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
        Schema::table('sending_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('imap_last_uid')->default(0);
            $table->timestamp('imap_last_checked_at')->nullable();
            $table->text('imap_last_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sending_accounts', function (Blueprint $table) {
            $table->dropColumn(['imap_last_uid', 'imap_last_checked_at', 'imap_last_error']);
        });
    }
};
