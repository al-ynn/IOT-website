<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) { $table->id(); $table->string('name'); $table->string('slug')->unique(); $table->timestamps(); });
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users','organization_id')) $table->foreignId('organization_id')->nullable()->index();
            if (!Schema::hasColumn('users','role')) $table->string('role')->default('viewer');
            if (!Schema::hasColumn('users','platform_role')) $table->string('platform_role')->nullable()->index();
        });
        Schema::table('users', function (Blueprint $table) { $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete(); });
        Schema::create('devices', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->string('external_id')->nullable()->unique(); $table->string('status')->default('offline'); $table->timestamps(); });
        Schema::create('dashboards', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->json('configuration')->nullable(); $table->timestamps(); });
    }
    public function down(): void { Schema::dropIfExists('dashboards'); Schema::dropIfExists('devices'); Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['organization_id'])); Schema::dropIfExists('organizations'); }
};
