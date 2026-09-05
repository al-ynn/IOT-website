<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) { $table->string('id')->primary(); $table->string('name'); $table->text('description')->nullable(); $table->decimal('price',12,2)->default(0); $table->string('currency',3)->default('USD'); $table->enum('interval',['monthly','yearly','lifetime']); $table->json('features'); $table->integer('device_limit')->default(0); $table->integer('user_limit')->default(0); $table->integer('dashboard_limit')->default(0); $table->boolean('active')->default(true); $table->timestamps(); });
        Schema::create('subscriptions', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->string('plan_id'); $table->foreign('plan_id')->references('id')->on('plans'); $table->string('status')->index(); $table->string('provider_transaction_id')->nullable()->index(); $table->boolean('cancel_at_period_end')->default(false); $table->timestamp('current_period_start')->nullable(); $table->timestamp('current_period_end')->nullable(); $table->timestamps(); $table->index(['organization_id','status']); });
    }
    public function down(): void { Schema::dropIfExists('subscriptions'); Schema::dropIfExists('plans'); }
};
