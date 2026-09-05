<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('device_event_facts', function (Blueprint $table) { $table->string('event_code', 80)->after('event_definition_id'); }); }
    public function down(): void { Schema::table('device_event_facts', fn (Blueprint $table) => $table->dropColumn('event_code')); }
};
