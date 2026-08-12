<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'payment_events',
            function (Blueprint $table) {

                $table->id();

                $table->string(
                    'event_id'
                )->unique();

                $table->string(
                    'event_type'
                );

                $table->string(
                    'transaction_id'
                )->nullable();

                $table->string(
                    'organization_id'
                )->nullable();

                $table->string('plan_id')->nullable();
                $table->decimal('amount', 12, 2)->nullable();
                $table->string('currency', 3)->nullable();
                $table->string('status')->default('received');
                $table->timestamp('processed_at')->nullable();

                $table->json(
                    'payload'
                )->nullable();

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'payment_events'
        );
    }
};
