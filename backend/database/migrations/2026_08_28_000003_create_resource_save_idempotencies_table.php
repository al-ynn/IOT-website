<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resource_save_idempotencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('operation_scope', 80);
            $table->char('key_hash', 64);
            $table->char('request_fingerprint', 64);
            $table->string('resource_type', 80);
            $table->string('resource_id', 100);
            $table->unsignedBigInteger('base_revision_id')->nullable();
            $table->boolean('changed')->default(false);
            $table->unsignedBigInteger('committed_revision_id')->nullable();
            $table->unsignedBigInteger('committed_revision_number')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['user_id', 'operation_scope', 'key_hash'], 'resource_save_idempotencies_actor_key_unique');
            $table->index(['expires_at', 'id']);
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_save_idempotencies');
    }
};
