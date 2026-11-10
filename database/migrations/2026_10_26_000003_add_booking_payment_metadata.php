<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('bookings',function(Blueprint $table){
  $table->unsignedBigInteger('deposit_amount_satang')->nullable();
  $table->unsignedBigInteger('refunded_amount_satang')->nullable();
  $table->boolean('is_test')->default(false);
  $table->boolean('payment_is_simulated')->default(false);
  $table->timestamp('payment_verified_at')->nullable();
  $table->foreignId('payment_verified_by')->nullable()->constrained('users')->nullOnDelete();
  $table->timestamp('test_marked_at')->nullable();
  $table->foreignId('test_marked_by')->nullable()->constrained('users')->nullOnDelete();
 }); }
 public function down(): void { Schema::table('bookings',function(Blueprint $table){
  $table->dropConstrainedForeignId('payment_verified_by');$table->dropConstrainedForeignId('test_marked_by');
  $table->dropColumn(['deposit_amount_satang','refunded_amount_satang','is_test','payment_is_simulated','payment_verified_at','test_marked_at']);
 }); }
};
