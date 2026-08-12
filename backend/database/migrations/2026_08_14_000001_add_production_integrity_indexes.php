<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $organizationForeignKeyExists = collect(Schema::getForeignKeys('users'))
            ->contains(fn (array $key) => in_array('organization_id', $key['columns'], true));

        if (! $organizationForeignKeyExists) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('organization_id', 'users_organization_integrity_foreign')
                    ->references('id')
                    ->on('organizations')
                    ->nullOnDelete();
            });
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(
                ['organization_id', 'user_id', 'read_at', 'created_at'],
                'notifications_inbox_index'
            );
        });

        Schema::table('automation_execution_logs', function (Blueprint $table) {
            $table->index(
                ['automation_execution_id', 'executed_at'],
                'automation_execution_logs_timeline_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('automation_execution_logs', function (Blueprint $table) {
            $table->dropIndex('automation_execution_logs_timeline_index');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_inbox_index');
        });

        $organizationForeignKeyExists = collect(Schema::getForeignKeys('users'))
            ->contains(fn (array $key) => ($key['name'] ?? null) === 'users_organization_integrity_foreign');

        if ($organizationForeignKeyExists) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign('users_organization_integrity_foreign');
            });
        }
    }
};
