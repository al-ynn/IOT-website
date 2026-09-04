<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_lifecycle_states', function (Blueprint $table) {
            $table->unsignedBigInteger('lifecycle_generation')->default(1)->after('state');
        });
        Schema::table('resource_revision_reminders', function (Blueprint $table) {
            $table->unsignedBigInteger('lifecycle_generation')->default(1)->after('generation');
        });
    }

    public function down(): void
    {
        Schema::table('resource_revision_reminders', function (Blueprint $table) {
            $table->dropColumn('lifecycle_generation');
        });
        Schema::table('resource_lifecycle_states', function (Blueprint $table) {
            $table->dropColumn('lifecycle_generation');
        });
    }
};
