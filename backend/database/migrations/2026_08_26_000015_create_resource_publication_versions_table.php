<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resource_publication_versions', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id');
            $table->unsignedInteger('publication_number');
            $table->foreignId('resource_revision_id')->constrained('resource_revisions')->restrictOnDelete();
            $table->foreignId('review_submission_id')->nullable()->constrained('resource_publication_submissions')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->foreignId('previous_publication_version_id')->nullable()->constrained('resource_publication_versions')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->unique(['resource_type', 'resource_id', 'publication_number'], 'publication_versions_resource_number');
            $table->unique('review_submission_id');
            $table->index(['resource_type', 'resource_id', 'published_at'], 'publication_versions_resource_published');
            $table->index('resource_revision_id');
        });
        Schema::table('resource_publication_states', function (Blueprint $table) {
            $table->foreignId('current_publication_version_id')->nullable()->after('approved_revision_id')->constrained('resource_publication_versions')->restrictOnDelete();
        });
        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('device_template_publication_version_id')->nullable()->after('device_template_revision_id')->constrained('resource_publication_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devices', fn (Blueprint $table) => $table->dropConstrainedForeignId('device_template_publication_version_id'));
        Schema::table('resource_publication_states', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_publication_version_id'));
        Schema::dropIfExists('resource_publication_versions');
    }
};
