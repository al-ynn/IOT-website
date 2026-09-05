<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('firmware_artifacts', function (Blueprint $table) {
            $table->id();$table->foreignId('organization_id')->constrained()->cascadeOnDelete();$table->foreignId('device_template_id')->nullable()->constrained()->nullOnDelete();$table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name',150);$table->string('version',80);$table->text('description')->nullable();$table->string('device_type',100)->nullable();$table->string('protocol',50)->nullable();$table->string('storage_disk',50);$table->string('storage_path',500);$table->string('original_filename',255);$table->string('mime_type',120);$table->unsignedBigInteger('size_bytes');$table->char('sha256',64);$table->timestamps();
            $table->unique(['organization_id','name','version']);$table->index(['organization_id','created_at']);$table->index('sha256');
        });
        Schema::create('firmware_deployments', function (Blueprint $table) {
            $table->id();$table->foreignId('organization_id')->constrained()->cascadeOnDelete();$table->foreignId('firmware_artifact_id')->constrained()->restrictOnDelete();$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$table->string('status',30)->default('delivery_unavailable');$table->timestamp('failed_at')->nullable();$table->string('failure_message',500)->nullable();$table->timestamps();$table->index(['organization_id','status','created_at']);
        });
        Schema::create('firmware_deployment_devices', function (Blueprint $table) {
            $table->id();$table->foreignId('firmware_deployment_id')->constrained()->cascadeOnDelete();$table->foreignId('device_id')->constrained()->cascadeOnDelete();$table->string('status',30)->default('delivery_unavailable');$table->timestamp('attempted_at')->nullable();$table->string('failure_code',80)->nullable();$table->string('failure_message',500)->nullable();$table->timestamps();$table->unique(['firmware_deployment_id','device_id']);$table->index(['device_id','status']);
        });
        Schema::table('operational_events', function (Blueprint $table) {$table->foreignId('firmware_deployment_id')->nullable()->constrained()->nullOnDelete();});
    }
    public function down(): void
    {
        Schema::table('operational_events',fn(Blueprint $table)=>$table->dropConstrainedForeignId('firmware_deployment_id'));Schema::dropIfExists('firmware_deployment_devices');Schema::dropIfExists('firmware_deployments');Schema::dropIfExists('firmware_artifacts');
    }
};
