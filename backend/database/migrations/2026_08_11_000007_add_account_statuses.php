<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{public function up():void{Schema::table('organizations',fn(Blueprint $t)=>$t->string('status')->default('active')->index());Schema::table('users',fn(Blueprint $t)=>$t->string('status')->default('active')->index());}public function down():void{Schema::table('users',fn(Blueprint $t)=>$t->dropColumn('status'));Schema::table('organizations',fn(Blueprint $t)=>$t->dropColumn('status'));}};
