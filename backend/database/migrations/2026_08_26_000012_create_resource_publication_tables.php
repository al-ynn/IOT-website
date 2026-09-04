<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resource_publication_states', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id');
            $table->foreignId('approved_revision_id')->nullable()->constrained('resource_revisions')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['resource_type', 'resource_id']);
            $table->index('approved_revision_id');
        });
        Schema::create('resource_publication_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id');
            $table->foreignId('submitted_revision_id')->constrained('resource_revisions')->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 32)->default('submitted');
            $table->string('active_key')->nullable()->unique();
            $table->timestamp('submitted_at');
            $table->foreignId('decision_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decision_at')->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->timestamps();
            $table->index(['resource_type', 'resource_id', 'status'], 'publication_submission_resource_status');
            $table->index(['status', 'submitted_at']);
        });
        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('device_template_revision_id')->nullable()->after('device_template_id')->constrained('resource_revisions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devices', fn (Blueprint $table) => $table->dropConstrainedForeignId('device_template_revision_id'));
        Schema::dropIfExists('resource_publication_submissions');
        Schema::dropIfExists('resource_publication_states');
    }
};
