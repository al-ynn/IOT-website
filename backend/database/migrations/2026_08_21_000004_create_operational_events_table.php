<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void { Schema::create('operational_events',function(Blueprint $table){
  $table->id();
  $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
  $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
  $table->boolean('device_bound')->default(false);
  $table->foreignId('automation_id')->nullable()->constrained()->nullOnDelete();
  $table->foreignId('automation_execution_id')->nullable()->constrained()->nullOnDelete();
  $table->string('source',30);$table->string('event_type',80);$table->string('severity',20);
  $table->string('title',120)->nullable();$table->string('message',500);$table->json('context')->nullable();
  $table->timestamp('occurred_at');$table->timestamps();
  $table->index(['organization_id','occurred_at']);$table->index(['device_id','occurred_at']);
  $table->index(['severity','occurred_at']);$table->index(['source','event_type','occurred_at']);
 }); }
 public function down():void { Schema::dropIfExists('operational_events'); }
};
