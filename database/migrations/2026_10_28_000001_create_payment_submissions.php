<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('payment_submissions',function(Blueprint $t){
   $t->id();$t->foreignId('booking_id')->constrained()->restrictOnDelete();
   $t->unsignedBigInteger('amount_satang');$t->string('method',40);$t->string('transaction_reference',200)->nullable();
   $t->string('proof_path')->nullable();$t->string('proof_name',200)->nullable();$t->string('status',30)->default('pending');
   $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('reviewed_at')->nullable();
   $t->text('review_notes')->nullable();$t->dateTime('submitted_at');$t->timestamps();$t->index(['booking_id','status']);
  });
  Schema::create('payment_reviews',function(Blueprint $t){
   $t->id();$t->foreignId('payment_submission_id')->constrained()->restrictOnDelete();
   $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();$t->string('decision',30);
   $t->text('notes');$t->string('verification_reference',200)->nullable()->unique();$t->dateTime('created_at');
  });
 }
 public function down():void {Schema::dropIfExists('payment_reviews');Schema::dropIfExists('payment_submissions');}
};
