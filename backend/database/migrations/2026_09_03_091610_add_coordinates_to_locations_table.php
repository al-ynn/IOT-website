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
        Schema::table('locations', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('description');
            $table->decimal('longitude', 11, 7)->nullable()->after('latitude');
            $table->index(['organization_id', 'latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'latitude', 'longitude']);
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
