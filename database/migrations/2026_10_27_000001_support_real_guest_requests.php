<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('treatment_requests',function(Blueprint $table){
   $table->foreignId('user_id')->nullable()->change();
   $table->text('date_of_birth')->nullable();
   $table->text('additional_notes')->nullable();
  });
  Schema::table('bookings',fn(Blueprint $table)=>$table->foreignId('user_id')->nullable()->change());
 }
 public function down(): void {
  // Removing nullable ownership while unclaimed guest records exist would lose data.
  if(\Illuminate\Support\Facades\DB::table('treatment_requests')->whereNull('user_id')->exists()||\Illuminate\Support\Facades\DB::table('bookings')->whereNull('user_id')->exists())throw new \RuntimeException('Claim guest records before reverting guest ownership support.');
  Schema::table('bookings',fn(Blueprint $table)=>$table->foreignId('user_id')->nullable(false)->change());
  Schema::table('treatment_requests',function(Blueprint $table){$table->foreignId('user_id')->nullable(false)->change();$table->dropColumn(['date_of_birth','additional_notes']);});
 }
};
