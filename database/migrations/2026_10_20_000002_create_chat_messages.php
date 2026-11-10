<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('treatment_requests',function(Blueprint $table){$table->string('selected_hospital_name')->nullable();$table->timestamp('hospital_approved_at')->nullable();$table->timestamp('payment_confirmed_at')->nullable();});
  Schema::create('chat_messages',function(Blueprint $table){$table->id();$table->foreignId('user_id')->constrained()->cascadeOnDelete();$table->foreignId('treatment_request_id')->nullable()->constrained()->cascadeOnDelete();$table->string('channel',20);$table->string('sender_type',20)->default('customer');$table->text('body');$table->timestamps();$table->index(['user_id','channel','id']);});
 }
 public function down(): void {Schema::dropIfExists('chat_messages');Schema::table('treatment_requests',function(Blueprint $table){$table->dropColumn(['selected_hospital_name','hospital_approved_at','payment_confirmed_at']);});}
};
