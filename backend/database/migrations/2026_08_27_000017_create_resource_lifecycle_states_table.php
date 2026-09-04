<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('resource_lifecycle_states', function (Blueprint $table) { $table->id();$table->string('resource_type',64);$table->unsignedBigInteger('resource_id');$table->string('state',16)->default('active');$table->foreignId('disabled_by')->nullable()->constrained('users')->nullOnDelete();$table->timestamp('disabled_at')->nullable();$table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();$table->timestamp('restored_at')->nullable();$table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();$table->timestamp('archived_at')->nullable();$table->timestamps();$table->unique(['resource_type','resource_id'],'resource_lifecycle_unique');$table->index(['resource_type','state','resource_id'],'resource_lifecycle_type_state');$table->index(['state','updated_at'],'resource_lifecycle_state_updated'); }); }
    public function down(): void { Schema::dropIfExists('resource_lifecycle_states'); }
};
