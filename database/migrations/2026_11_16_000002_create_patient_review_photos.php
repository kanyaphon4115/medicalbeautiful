<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('patient_review_photos',function(Blueprint $t){
  $t->id();$t->foreignId('user_id')->constrained();$t->foreignId('patient_review_id')->nullable()->constrained()->nullOnDelete();
  $t->string('path');$t->timestamps();
 });}
 public function down():void{Schema::dropIfExists('patient_review_photos');}
};
