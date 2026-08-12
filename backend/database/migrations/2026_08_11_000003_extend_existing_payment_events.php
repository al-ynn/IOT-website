<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void { Schema::table('payment_events',function(Blueprint $table){ if(!Schema::hasColumn('payment_events','plan_id'))$table->string('plan_id')->nullable();if(!Schema::hasColumn('payment_events','amount'))$table->decimal('amount',12,2)->nullable();if(!Schema::hasColumn('payment_events','currency'))$table->string('currency',3)->nullable();if(!Schema::hasColumn('payment_events','status'))$table->string('status')->default('received');if(!Schema::hasColumn('payment_events','processed_at'))$table->timestamp('processed_at')->nullable(); }); }
 public function down(): void { Schema::table('payment_events',fn(Blueprint $table)=>$table->dropColumn(['plan_id','amount','currency','status','processed_at'])); }
};
