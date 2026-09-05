<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resource_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 64);
            $table->unsignedBigInteger('resource_id');
            $table->unsignedInteger('revision_number');
            $table->foreignId('parent_revision_id')->nullable()->constrained('resource_revisions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_summary', 255);
            $table->unsignedSmallInteger('snapshot_schema_version')->default(1);
            $table->json('snapshot');
            $table->json('changed_sections');
            $table->char('checksum', 64);
            $table->timestamp('created_at');
            $table->unique(['resource_type', 'resource_id', 'revision_number'], 'resource_revision_sequence_unique');
            $table->index(['resource_type', 'resource_id', 'created_at'], 'resource_revision_history_index');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_revisions');
    }
};
