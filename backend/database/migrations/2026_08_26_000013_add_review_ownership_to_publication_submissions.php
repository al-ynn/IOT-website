<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('resource_publication_submissions', function (Blueprint $table) {
            $table->foreignId('reviewer_id')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('review_claimed_at')->nullable()->after('reviewer_id');
            $table->index(['status', 'reviewer_id']);
            $table->index(['reviewer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('resource_publication_submissions', function (Blueprint $table) {
            $table->dropIndex(['status', 'reviewer_id']);
            $table->dropIndex(['reviewer_id', 'status']);
            $table->dropConstrainedForeignId('reviewer_id');
            $table->dropColumn('review_claimed_at');
        });
    }
};
