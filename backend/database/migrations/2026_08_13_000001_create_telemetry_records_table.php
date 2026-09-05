<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('telemetry_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->decimal('value', 20, 6);
            $table->string('unit', 50)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['device_id', 'recorded_at']);
            $table->index(['device_id', 'key', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_records');
    }
};
