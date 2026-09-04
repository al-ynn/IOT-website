<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_access_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('access_level', 20);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['device_id', 'user_id']);
            $table->index(['user_id', 'access_level']);
            $table->index(['device_id', 'access_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_access_assignments');
    }
};
