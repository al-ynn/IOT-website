<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resource_attention_states', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 64);
            $table->unsignedBigInteger('resource_id');
            $table->string('status', 16)->default('open');
            $table->string('reason_code', 64);
            $table->text('note');
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['resource_type', 'resource_id']);
            $table->index(['status', 'marked_at']);
            $table->index(['resource_type', 'status', 'marked_at']);
            $table->index(['reason_code', 'status']);
            $table->index(['marked_by', 'marked_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('resource_attention_states'); }
};
