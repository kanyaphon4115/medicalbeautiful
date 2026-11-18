<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('patient_reviews',function(Blueprint $table){
  $table->id();$table->foreignId('user_id')->constrained();$table->foreignId('booking_id')->unique()->constrained();
  $table->string('procedure',120);$table->unsignedTinyInteger('rating');$table->string('title',150);$table->text('body');
  $table->string('status',20)->default('pending')->index();$table->boolean('photo_consent')->default(false);
  $table->string('before_path')->nullable();$table->string('after_path')->nullable();$table->timestamp('published_at')->nullable();$table->timestamps();
 });}
 public function down():void {Schema::dropIfExists('patient_reviews');}
};
