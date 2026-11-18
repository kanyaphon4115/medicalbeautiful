<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::table('patient_reviews',function(Blueprint $t){
  $t->boolean('anonymous')->default(false);$t->foreignId('reviewer_id')->nullable()->constrained('users');
  $t->timestamp('reviewed_at')->nullable();$t->text('moderation_note')->nullable();
 });}
 public function down():void{Schema::table('patient_reviews',function(Blueprint $t){$t->dropConstrainedForeignId('reviewer_id');$t->dropColumn(['anonymous','reviewed_at','moderation_note']);});}
};
