<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void{Schema::table('plans',fn(Blueprint $t)=>$t->boolean('is_default')->default(false)->index());}public function down():void{Schema::table('plans',fn(Blueprint $t)=>$t->dropColumn('is_default'));}};
