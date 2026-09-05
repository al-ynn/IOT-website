<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_template_metadata_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_template_id')->constrained('device_templates')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('name', 120);
            $table->string('data_type', 24);
            $table->text('description')->nullable();
            $table->boolean('required')->default(false);
            $table->json('configuration')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['device_template_id', 'key']);
        });

        Schema::create('device_metadata_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('metadata_definition_id')->constrained('device_template_metadata_definitions')->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['device_id', 'metadata_definition_id']);
        });

        Schema::create('device_template_event_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_template_id')->constrained('device_templates')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name', 120);
            $table->string('severity', 16)->default('info');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['device_template_id', 'code']);
        });

        Schema::create('device_event_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('event_definition_id')->constrained('device_template_event_definitions')->restrictOnDelete();
            $table->string('severity', 16);
            $table->string('event_name', 120);
            $table->text('message')->nullable();
            $table->json('value')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['device_id', 'occurred_at']);
            $table->index(['event_definition_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_event_facts');
        Schema::dropIfExists('device_template_event_definitions');
        Schema::dropIfExists('device_metadata_values');
        Schema::dropIfExists('device_template_metadata_definitions');
    }
};
