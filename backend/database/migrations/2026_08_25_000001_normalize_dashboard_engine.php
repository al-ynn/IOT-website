<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->change();
            $table->foreignId('owner_user_id')->nullable()->after('organization_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope_type', 32)->default('personal')->after('owner_user_id');
            $table->boolean('is_default')->default(false)->after('scope_type');
            $table->unsignedInteger('layout_version')->default(1)->after('is_default');
            $table->foreignId('created_by')->nullable()->after('layout_version')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->index(['owner_user_id', 'scope_type']);
        });

        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('dashboard_id')->constrained()->cascadeOnDelete();
            $table->string('widget_type', 64);
            $table->string('title', 100)->nullable();
            $table->json('layout');
            $table->json('configuration');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['dashboard_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
        Schema::table('dashboards', function (Blueprint $table) {
            $table->dropForeign(['owner_user_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropIndex(['owner_user_id', 'scope_type']);
            $table->dropColumn(['owner_user_id', 'scope_type', 'is_default', 'layout_version', 'created_by', 'updated_by']);
        });
    }
};
