<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resource_review_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('resource_publication_submissions')->restrictOnDelete();
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id');
            $table->string('event_type', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resource_revision_id')->nullable()->constrained('resource_revisions')->restrictOnDelete();
            $table->foreignId('previous_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('previous_approved_revision_id')->nullable()->constrained('resource_revisions')->restrictOnDelete();
            $table->foreignId('approved_revision_id')->nullable()->constrained('resource_revisions')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->string('event_key')->unique();
            $table->timestamp('created_at');
            $table->index(['submission_id', 'created_at']);
            $table->index(['resource_type', 'resource_id', 'created_at'], 'review_events_resource_created');
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_review_events');
    }
};
