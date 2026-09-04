<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            $table->foreignId('device_id')->nullable()->after('organization_id')->constrained()->cascadeOnDelete();
            $table->unique('device_id');
            $table->index(['scope_type', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            $table->dropUnique(['device_id']);
            $table->dropIndex(['scope_type', 'device_id']);
            $table->dropConstrainedForeignId('device_id');
        });
    }
};
