<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_outbox_events', function (Blueprint $table) {
            $table->json('payload')->nullable()->after('aggregate_id');
        });
    }

    public function down(): void
    {
        Schema::table('notification_outbox_events', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
