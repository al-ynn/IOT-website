<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('device_templates', function (Blueprint $table) {
            $table->json('dashboard_configuration')->nullable()->after('protocol');
        });
    }

    public function down(): void
    {
        Schema::table('device_templates', fn (Blueprint $table) => $table->dropColumn('dashboard_configuration'));
    }
};
