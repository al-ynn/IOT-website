<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedSmallInteger('schema_version')->default(1)->after('type');
            $table->string('category')->default('updates')->after('schema_version')->index();
            $table->boolean('requires_action')->default(false)->after('data');
            $table->string('action_state')->nullable()->after('requires_action');
            $table->timestamp('dismissed_at')->nullable()->after('read_at')->index();
            $table->string('deduplication_key')->nullable()->after('dismissed_at')->unique();
            $table->index(['user_id', 'dismissed_at', 'created_at']);
            $table->index(['user_id', 'read_at']);
        });
        DB::table('notifications')->whereNull('type')->update(['type' => 'legacy.notification', 'category' => 'updates']);
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique(['deduplication_key']);
            $table->dropIndex(['user_id', 'dismissed_at', 'created_at']);
            $table->dropIndex(['user_id', 'read_at']);
            $table->dropIndex(['category']);
            $table->dropColumn(['schema_version', 'category', 'requires_action', 'action_state', 'dismissed_at', 'deduplication_key']);
        });
    }
};
