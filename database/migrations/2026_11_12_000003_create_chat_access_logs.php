<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('chat_access_logs',function(Blueprint $t){
  $t->id();$t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();$t->string('actor_role',20);
  $t->string('conversation_type',20);$t->string('action',40);$t->boolean('permitted');$t->string('purpose',40);
  $t->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
  $t->foreignId('treatment_request_id')->nullable()->constrained()->nullOnDelete();
  $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();$t->timestamp('created_at');
  $t->index(['actor_id','created_at']);$t->index(['conversation_type','customer_id','created_at']);
 });}
 public function down():void {Schema::dropIfExists('chat_access_logs');}
};
