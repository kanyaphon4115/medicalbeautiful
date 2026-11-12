<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
 Schema::table('bookings',function(Blueprint $t){$t->string('deposit_phase')->default('initial');$t->unsignedTinyInteger('deposit_percentage')->nullable();});
 Schema::table('payment_submissions',fn(Blueprint $t)=>$t->string('purpose')->default('initial'));
 Schema::table('users',fn(Blueprint $t)=>$t->unsignedTinyInteger('treatment_deposit_percentage')->default(10));
 }
 public function down(): void {
 Schema::table('bookings',fn(Blueprint $t)=>$t->dropColumn(['deposit_phase','deposit_percentage']));
 Schema::table('payment_submissions',fn(Blueprint $t)=>$t->dropColumn('purpose'));
 Schema::table('users',fn(Blueprint $t)=>$t->dropColumn('treatment_deposit_percentage'));
 }
};