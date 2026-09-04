<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('provisioning_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'created_at']);
            $table->index(['status', 'expires_at']);
        });

        Schema::table('operational_events', function (Blueprint $table) {
            $table->foreignId('provisioning_session_id')->nullable()->after('automation_execution_id')->constrained('provisioning_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('operational_events', fn (Blueprint $table) => $table->dropConstrainedForeignId('provisioning_session_id'));
        Schema::dropIfExists('provisioning_sessions');
    }
};
