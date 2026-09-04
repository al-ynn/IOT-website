<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resource_share_requests', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 64);
            $table->unsignedBigInteger('resource_id');
            $table->foreignId('sender_user_id')->constrained('users');
            $table->foreignId('recipient_user_id')->constrained('users');
            $table->string('requested_permission', 32);
            $table->string('final_permission', 32)->nullable();
            $table->string('status', 32)->default('pending_recipient');
            $table->string('active_key')->nullable()->unique();
            $table->string('note', 1000)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['recipient_user_id', 'status']);
            $table->index(['sender_user_id', 'status']);
            $table->index(['resource_type', 'resource_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_share_requests');
    }
};
