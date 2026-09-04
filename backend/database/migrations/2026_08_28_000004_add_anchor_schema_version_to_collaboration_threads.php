<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaboration_threads', function (Blueprint $table) {
            $table->unsignedSmallInteger('anchor_schema_version')->default(1)->after('anchor_revision_id');
            $table->index(['anchor_revision_id', 'anchor_schema_version'], 'collaboration_threads_anchor_revision_schema_index');
        });
    }

    public function down(): void
    {
        Schema::table('collaboration_threads', function (Blueprint $table) {
            $table->dropIndex('collaboration_threads_anchor_revision_schema_index');
            $table->dropColumn('anchor_schema_version');
        });
    }
};
