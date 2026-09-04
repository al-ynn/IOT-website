<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('organization_id')->constrained('users')->nullOnDelete();
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('type')->nullable()->after('user_id')->index();
            $table->foreignId('actor_id')->nullable()->after('type')->constrained('users')->nullOnDelete();
            $table->string('resource_type')->nullable()->after('actor_id');
            $table->unsignedBigInteger('resource_id')->nullable()->after('resource_type');
            $table->string('action_url')->nullable()->after('resource_id');
            $table->json('data')->nullable()->after('action_url');
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['actor_id']);
            $table->dropIndex(['resource_type', 'resource_id']);
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'actor_id', 'resource_type', 'resource_id', 'action_url', 'data']);
        });
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
