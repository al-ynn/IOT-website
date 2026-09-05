<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crash_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_report_id', 100)->nullable();
            $table->string('crash_type', 80);
            $table->string('reason', 500)->nullable();
            $table->string('message', 1000)->nullable();
            $table->string('firmware_version', 100)->nullable();
            $table->string('runtime_version', 100)->nullable();
            $table->unsignedBigInteger('uptime_seconds')->nullable();
            $table->string('reboot_reason', 255)->nullable();
            $table->text('stack_trace')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->unique(['device_id', 'client_report_id']);
            $table->index(['organization_id', 'received_at']);
            $table->index(['device_id', 'received_at']);
            $table->index(['crash_type', 'received_at']);
            $table->index(['firmware_version', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crash_reports');
    }
};
